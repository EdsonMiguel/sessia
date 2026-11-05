<?php

namespace App\Livewire\Auth;

use App\Modules\Identity\Actions\RegisterBusiness;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Register extends Component
{
    public string $name = '';

    public string $businessName = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(RegisterBusiness $action): void
    {
        abort_if(Auth::check(), 403);
        $key = 'register:'.hash('sha256', request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Muitas tentativas de cadastro. Aguarde um minuto e tente novamente.']);
        }
        RateLimiter::hit($key, 60);

        $this->email = mb_strtolower(trim($this->email));
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'businessName' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $action->handle($data['name'], $data['email'], $data['password'], $data['businessName']);
        Auth::login($user);
        session()->regenerate();
        session()->forget('current_tenant_id');
        $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
