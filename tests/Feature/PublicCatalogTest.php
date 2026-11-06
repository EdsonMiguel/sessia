<?php

use App\Livewire\Catalog\ProcedureForm;
use App\Livewire\Platform\BusinessSettings;
use App\Models\Procedure;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->owner = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['name' => 'Espaço Público', 'slug' => 'espaco-publico', 'phone' => '+5511999991234', 'pix_key' => 'segredo-pix@example.test']);
    $this->owner->tenants()->attach($this->tenant, ['role' => 'owner']);
    $this->actingAs($this->owner);
    session()->forget('current_tenant_id');
    $this->published = Procedure::factory()->create(['name' => 'Serviço Público', 'description' => 'Descrição pública', 'show_in_catalog' => true]);
    $this->hidden = Procedure::factory()->create(['name' => 'Serviço Privado']);
    $this->inactive = Procedure::factory()->create(['name' => 'Serviço Inativo', 'is_active' => false, 'show_in_catalog' => true]);
    $this->otherOwner = User::factory()->create();
    $this->otherTenant = Tenant::factory()->create(['name' => 'Outro Negócio', 'slug' => 'outro-negocio', 'phone' => '+5521999991234']);
    $this->otherOwner->tenants()->attach($this->otherTenant, ['role' => 'owner']);
    $this->actingAs($this->otherOwner);
    session()->forget('current_tenant_id');
    $this->foreign = Procedure::factory()->create(['name' => 'Outro Serviço', 'show_in_catalog' => true]);
    Auth::logout();
    session()->forget('current_tenant_id');
});

it('shows only opted in active services of the slug tenant without login or private data', function () {
    $this->get('/catalogo/espaco-publico?tenant_id='.$this->otherTenant->id)->assertOk()
        ->assertSeeInOrder([$this->tenant->name, 'By '.$this->owner->name])
        ->assertSee('Serviço Público')->assertSee('Descrição pública')->assertSee('R$ 120,00')
        ->assertDontSee('Serviço Privado')->assertDontSee('Serviço Inativo')->assertDontSee('Outro Serviço')
        ->assertDontSee('segredo-pix@example.test')->assertDontSee($this->owner->email);
    $this->assertGuest();
    $this->get('/clientes')->assertRedirect(route('login'));
    $this->get('/catalogo/inexistente')->assertNotFound();
});

it('uses the public slug even when a visitor is authenticated in a different tenant', function () {
    $this->actingAs($this->otherOwner);
    session(['current_tenant_id' => $this->otherTenant->id]);
    $this->get('/catalogo/espaco-publico')->assertOk()->assertSee('Serviço Público')->assertDontSee('Outro Serviço')
        ->assertSee('By '.$this->owner->name)->assertDontSee('By '.$this->otherOwner->name);
    expect(session('current_tenant_id'))->toBe($this->otherTenant->id);
});

it('omits the catalog byline when the business has no owner', function () {
    $this->tenant->users()->updateExistingPivot($this->owner->id, ['role' => 'member']);

    $this->get('/catalogo/espaco-publico')->assertOk()
        ->assertSee($this->tenant->name)->assertDontSee('By '.$this->owner->name);
});

it('opens WhatsApp with the selected public service and the business phone', function () {
    $response = $this->get('/catalogo/espaco-publico/contato?procedure='.$this->published->id)->assertRedirect();
    $url = $response->headers->get('Location');
    expect(parse_url($url, PHP_URL_HOST))->toBe('wa.me')
        ->and(parse_url($url, PHP_URL_PATH))->toBe('/5511999991234');
    parse_str(parse_url($url, PHP_URL_QUERY), $params);
    expect($params['text'])->toContain('Serviço Público', 'Espaço Público', 'mais informações');
});

it('rejects private inactive foreign or invalid service selections', function () {
    foreach ([$this->hidden->id, $this->inactive->id, $this->foreign->id, 'invalid', '999999'] as $id) {
        $this->get('/catalogo/espaco-publico/contato?procedure='.$id)->assertNotFound();
    }
    $this->get('/catalogo/espaco-publico/contato?procedure[]=1')->assertNotFound();
    $this->get('/catalogo/espaco-publico/contato')->assertNotFound();
});

it('renders an empty catalog and removes unpublished procedures from contact immediately', function () {
    $this->actingAs($this->owner);
    $this->published->update(['show_in_catalog' => false]);
    Auth::logout();
    $this->get('/catalogo/espaco-publico')->assertOk()->assertSee('Novos cuidados em breve');
    $this->get('/catalogo/espaco-publico/contato?procedure='.$this->published->id)->assertNotFound();
});

it('saves a unique slug and normalized phone only for the authorized business', function () {
    $this->actingAs($this->owner);
    Livewire::test(BusinessSettings::class)->set('slug', ' NOVO-ESPACO ')->set('phone', '(11) 98888-1234')
        ->set('pix_key', '')->call('save')->assertHasNoErrors();
    expect($this->tenant->fresh()->slug)->toBe('novo-espaco')->and($this->tenant->fresh()->phone)->toBe('+5511988881234')
        ->and($this->otherTenant->fresh()->slug)->toBe('outro-negocio');
    $this->get('/catalogo/espaco-publico')->assertNotFound();
    $this->get('/catalogo/novo-espaco')->assertOk();
    Livewire::test(BusinessSettings::class)->set('slug', 'outro-negocio')->call('save')->assertHasErrors('slug');
    Livewire::test(BusinessSettings::class)->set('slug', 'invalido/slug')->call('save')->assertHasErrors('slug');
    Livewire::test(BusinessSettings::class)->set('phone', '')->call('save')->assertHasErrors('phone');
    Livewire::test(BusinessSettings::class)->set('phone', 'sem-telefone')->call('save')->assertHasErrors('phone');
});

it('allows disabling the public URL and enforces uniqueness in the database', function () {
    expect(fn () => $this->otherTenant->update(['slug' => 'espaco-publico']))->toThrow(QueryException::class);
    $this->actingAs($this->owner);
    Livewire::test(BusinessSettings::class)->set('slug', '')->set('pix_key', '')->call('save')->assertHasNoErrors();
    expect($this->tenant->fresh()->slug)->toBeNull();
    $this->get('/catalogo/espaco-publico')->assertNotFound();
});

it('persists the catalog indicator through the procedure form and blocks cross tenant edits', function () {
    $this->actingAs($this->owner);
    Livewire::test(ProcedureForm::class, ['procedureId' => $this->hidden->id])
        ->assertSet('show_in_catalog', false)->set('show_in_catalog', true)->call('save')->assertHasNoErrors();
    expect($this->hidden->fresh()->show_in_catalog)->toBeTrue();
    Livewire::test(ProcedureForm::class, ['procedureId' => $this->foreign->id])->assertNotFound();
});

it('escapes service content and paginates the public selection', function () {
    $this->actingAs($this->owner);
    $this->published->update(['description' => '<script>alert(1)</script>']);
    Procedure::factory()->count(12)->create(['show_in_catalog' => true]);
    Auth::logout();
    $this->get('/catalogo/espaco-publico')->assertOk()->assertViewHas('procedures', fn ($list) => $list->count() === 12 && $list->total() === 13);
    $this->get('/catalogo/espaco-publico?page=2')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
});
