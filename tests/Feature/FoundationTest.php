<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Platform\BusinessSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Identity\Actions\RegisterBusiness;
use App\Modules\Platform\CurrentTenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    config(['app.locale' => 'pt_BR']);
    app()->setLocale('pt_BR');
    $this->withoutVite();
    RateLimiter::clear('register:'.hash('sha256', '127.0.0.1'));
});

function businessOwner(string $name = 'Espaço de teste'): User
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['name' => $name]);
    $user->tenants()->attach($tenant, ['role' => 'owner']);

    return $user;
}

it('redirects visitors and renders public authentication screens', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get('/inicio')->assertRedirect(route('login'));
    $this->get('/configuracoes')->assertRedirect(route('login'));
    $this->get('/entrar')->assertOk()->assertSee('Bem-vinda de volta');
    $this->get('/cadastro')->assertOk()->assertSee('Seu negócio começa aqui');
});

it('registers an owner and a business together and hashes the password', function () {
    Livewire::test(Register::class)
        ->set('name', 'Profissional de Teste')
        ->set('businessName', 'Estética de Teste')
        ->set('email', 'PROFISSIONAL@example.test')
        ->set('password', 'SenhaTeste123')
        ->set('password_confirmation', 'SenhaTeste123')
        ->call('register')->assertHasNoErrors()->assertRedirect(route('dashboard'));

    $user = User::sole();
    expect($user->email)->toBe('profissional@example.test');
    expect(Hash::check('SenhaTeste123', $user->password))->toBeTrue();
    expect($user->tenants()->sole()->pivot->role)->toBe('owner');
    expect(Tenant::sole()->timezone)->toBe('America/Sao_Paulo');
    $this->assertAuthenticatedAs($user);
    $this->get('/inicio')->assertOk()->assertSee('Estética de Teste');
});

it('validates registration without creating partial records', function () {
    Livewire::test(Register::class)
        ->set('email', 'invalido')->set('password', 'abc')
        ->call('register')->assertHasErrors(['name', 'businessName', 'email', 'password']);

    expect(User::count())->toBe(0)->and(Tenant::count())->toBe(0);
});

it('rejects duplicate email and mismatched password confirmation', function () {
    $user = User::factory()->create();
    Livewire::test(Register::class)
        ->set('name', 'Teste')->set('businessName', 'Negócio teste')
        ->set('email', $user->email)->set('password', 'SenhaTeste123')
        ->set('password_confirmation', 'SenhaDiferente123')
        ->call('register')->assertHasErrors(['email', 'password']);
    expect(Tenant::count())->toBe(0);
});

it('limits registration attempts on Livewire actions', function () {
    $component = Livewire::test(Register::class);
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $component->call('register');
    }
    $component->assertHasErrors('email')->assertSee('Muitas tentativas de cadastro');
    expect(User::count())->toBe(0);
});

it('rejects stale public authentication actions for an authenticated user', function () {
    $owner = businessOwner();
    Livewire::actingAs($owner)->test(Register::class)->call('register')->assertForbidden();
    Livewire::actingAs($owner)->test(Login::class)->call('login')->assertForbidden();
    expect(User::count())->toBe(1)->and(Tenant::count())->toBe(1);
});

it('refuses settings changes from a member without owner permission', function () {
    $owner = businessOwner();
    $member = User::factory()->create();
    $member->tenants()->attach($owner->tenants()->sole(), ['role' => 'member']);
    $this->actingAs($member)->get('/configuracoes')->assertForbidden();
});

it('rolls back the user and business if membership creation fails', function () {
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'insert into') && str_contains($query->sql, 'tenant_user')) {
            throw new RuntimeException('Membership failure for transaction test');
        }
    });

    expect(fn () => app(RegisterBusiness::class)->handle('Teste', 'rollback@example.test', 'SenhaTeste123', 'Teste'))
        ->toThrow(RuntimeException::class);
    expect(User::count())->toBe(0)->and(Tenant::count())->toBe(0);
});

it('logs in and clears an old tenant context', function () {
    $user = businessOwner();
    session(['current_tenant_id' => 999999]);
    Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')
        ->set('remember', true)->call('login')->assertHasNoErrors()->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $this->get('/inicio')->assertOk();
    expect(session('current_tenant_id'))->toBe($user->tenants()->sole()->id);
});

it('rejects incorrect login credentials', function () {
    $user = businessOwner();
    Livewire::test(Login::class)->set('email', $user->email)->set('password', 'incorrect')
        ->call('login')->assertHasErrors('email');
    $this->assertGuest();
});

it('limits repeated failed login attempts', function () {
    $component = Livewire::test(Login::class)->set('email', 'limited@example.test')->set('password', 'incorrect');
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $component->call('login')->assertHasErrors('email');
    }
    $component->assertSee('Muitas tentativas');
    RateLimiter::clear('login:'.hash('sha256', 'limited@example.test|127.0.0.1'));
});

it('logs out and removes tenant context', function () {
    $user = businessOwner();
    $this->actingAs($user)->withSession(['current_tenant_id' => $user->tenants()->sole()->id]);
    $this->post('/sair')->assertRedirect(route('login'));
    $this->assertGuest();
    expect(session()->has('current_tenant_id'))->toBeFalse();
    $this->get('/inicio')->assertRedirect(route('login'));
});

it('does not expose another business through query parameters', function () {
    $owner = businessOwner('Negócio A');
    $other = businessOwner('Negócio B confidencial')->tenants()->sole();
    $this->actingAs($owner)->get('/configuracoes?tenant_id='.$other->id)
        ->assertOk()->assertSee('Negócio A')->assertDontSee('Negócio B confidencial');
    expect(app(CurrentTenant::class)->get()->id)->toBe($owner->tenants()->sole()->id);
    expect(Gate::allows('update', $other))->toBeFalse();
});

it('refuses a session pointing to another business', function () {
    $owner = businessOwner();
    $other = businessOwner()->tenants()->sole();
    $this->actingAs($owner)->withSession(['current_tenant_id' => $other->id]);
    $this->get('/inicio')->assertForbidden();
    $this->get('/configuracoes')->assertForbidden();
});

it('fails closed without a membership', function () {
    $this->actingAs(User::factory()->create())->get('/inicio')->assertForbidden();
});

it('updates only the authenticated business through Livewire', function () {
    $owner = businessOwner('Negócio A');
    $other = businessOwner('Negócio B')->tenants()->sole();
    Livewire::actingAs($owner)->test(BusinessSettings::class)
        ->set('name', 'Novo nome A')->set('timezone', 'America/Manaus')
        ->call('save')->assertHasNoErrors()->assertSee('Dados do negócio atualizados.');
    expect($owner->tenants()->sole()->name)->toBe('Novo nome A');
    expect($other->fresh()->name)->toBe('Negócio B');
});

it('validates business settings in Portuguese', function () {
    Livewire::actingAs(businessOwner())->test(BusinessSettings::class)
        ->set('name', '')->set('timezone', 'invalid')
        ->call('save')->assertHasErrors(['name', 'timezone'])
        ->assertSee('Informe nome.')->assertSee('Escolha um fuso horário válido.');
});

it('rechecks membership on Livewire actions after access is revoked', function () {
    $owner = businessOwner('Negócio protegido');
    $tenant = $owner->tenants()->sole();
    $component = Livewire::actingAs($owner)->test(BusinessSettings::class);
    $owner->tenants()->detach($tenant);
    $component->set('name', 'Alteração indevida')->call('save')->assertForbidden();
    expect($tenant->fresh()->name)->toBe('Negócio protegido');
});

it('does not retain context when a different user authenticates', function () {
    $first = businessOwner();
    $second = businessOwner();
    $this->actingAs($first);
    expect(app(CurrentTenant::class)->get()->id)->toBe($first->tenants()->sole()->id);
    Auth::logout();
    session()->forget('current_tenant_id');
    $this->actingAs($second);
    expect(app(CurrentTenant::class)->get()->id)->toBe($second->tenants()->sole()->id);
});

it('prevents duplicate memberships in the database', function () {
    $owner = businessOwner();
    expect(fn () => $owner->tenants()->attach($owner->tenants()->sole(), ['role' => 'owner']))
        ->toThrow(QueryException::class);
});

it('prevents membership references to a missing business', function () {
    $user = User::factory()->create();
    expect(fn () => $user->tenants()->attach(999999, ['role' => 'owner']))->toThrow(QueryException::class);
});
