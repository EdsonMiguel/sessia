<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.show', ['email' => $request->user()->email]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['bail', 'required', 'string', 'regex:/\A[^\x00]{1,72}\z/', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'password.regex' => 'A nova senha é longa demais ou contém um caractere inválido.',
            'password.confirmed' => 'A confirmação da nova senha não confere.',
        ], [
            'current_password' => 'a senha atual',
            'password' => 'a nova senha',
        ]);

        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
                throw ValidationException::withMessages(['current_password' => 'A senha atual está incorreta.']);
            }

            $user->password = Hash::make($data['password']);
            $user->setRememberToken(Str::random(60));
            $user->save();
        });

        $request->session()->regenerate(true);

        return to_route('account.show')->with('status', 'Senha alterada com sucesso.');
    }
}
