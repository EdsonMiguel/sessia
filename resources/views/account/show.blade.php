<x-layouts.app>
    <p class="eyebrow">Seus dados de acesso</p>
    <h1 class="page-title">Minha conta</h1>

    <section class="mt-8 max-w-2xl rounded-2xl border border-stone-200 bg-white p-4 sm:p-8">
        <dl>
            <dt class="text-sm font-medium text-stone-600">E-mail cadastrado</dt>
            <dd class="mt-2 break-all font-semibold">{{ $email }}</dd>
        </dl>

        <form method="POST" action="{{ route('account.password.update') }}" class="mt-6 space-y-4 border-t border-stone-100 pt-6" aria-labelledby="change-password-title">
            @csrf
            @method('PUT')
            <h2 id="change-password-title" class="font-semibold">Alterar senha</h2>
            <p class="text-sm text-stone-600">Use pelo menos 8 caracteres, com letras e números.</p>
            <x-flash />
            <x-form.input name="current_password" label="Senha atual" type="password" autocomplete="current-password" required />
            <x-form.input name="password" label="Nova senha" type="password" autocomplete="new-password" minlength="8" required />
            <x-form.input name="password_confirmation" label="Confirme a nova senha" type="password" autocomplete="new-password" minlength="8" required />
            <button class="primary-button">Alterar senha</button>
        </form>
    </section>
</x-layouts.app>
