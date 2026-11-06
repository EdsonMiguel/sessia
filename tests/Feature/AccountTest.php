<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['remember_token' => 'previous-remember-token']);
    $this->otherUser = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $this->user->tenants()->attach($tenant, ['role' => 'member']);
    $this->otherUser->tenants()->attach($tenant, ['role' => 'owner']);
});

it('requires authentication to view the account or change the password', function () {
    $this->get(route('account.show'))->assertRedirect(route('login'));
    $this->put(route('account.password.update'))->assertRedirect(route('login'));
});

it('shows the signed in member their own email', function () {
    $this->actingAs($this->user)
        ->get(route('account.show'))
        ->assertOk()
        ->assertSee('E-mail cadastrado')
        ->assertSee($this->user->email)
        ->assertDontSee($this->otherUser->email);
});

it('changes only the signed in user password and preserves their session', function () {
    $otherPassword = $this->otherUser->password;

    $this->actingAs($this->user)->put(route('account.password.update'), [
        'user_id' => $this->otherUser->id,
        'current_password' => 'password',
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ])->assertRedirect(route('account.show'))->assertSessionHas('status', 'Senha alterada com sucesso.');

    $this->assertAuthenticatedAs($this->user);
    expect(Auth::validate(['email' => $this->user->email, 'password' => 'NovaSenha123']))->toBeTrue()
        ->and(Auth::validate(['email' => $this->user->email, 'password' => 'password']))->toBeFalse()
        ->and($this->otherUser->fresh()->password)->toBe($otherPassword)
        ->and($this->user->fresh()->getRememberToken())->not->toBe('previous-remember-token');
});

it('rejects invalid password changes without retaining credentials', function (array $overrides, string $field) {
    $password = $this->user->password;

    $this->actingAs($this->user)->from(route('account.show'))
        ->put(route('account.password.update'), array_replace([
            'current_password' => 'password',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ], $overrides))
        ->assertRedirect(route('account.show'))
        ->assertSessionHasErrors($field)
        ->assertSessionMissing('_old_input.current_password')
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');

    expect($this->user->fresh()->password)->toBe($password);
})->with([
    'incorrect current password' => [['current_password' => 'incorrect'], 'current_password'],
    'mismatched confirmation' => [['password_confirmation' => 'outra'], 'password'],
    'weak password' => [['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'], 'password'],
    'password over bcrypt limit' => [['password' => str_repeat('á', 36).'1', 'password_confirmation' => str_repeat('á', 36).'1'], 'password'],
]);
