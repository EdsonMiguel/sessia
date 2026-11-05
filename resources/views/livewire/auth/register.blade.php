<div>
    <h2 class="text-2xl font-semibold tracking-tight">Seu negócio começa aqui</h2>
    <p class="mt-2 text-sm text-stone-500">Crie sua conta e o espaço do seu negócio.</p>
    <form wire:submit="register" class="mt-6 space-y-4">
        <div>
            <label for="name" class="field-label">Seu nome</label>
            <input id="name" wire:model="name" autocomplete="name" required maxlength="120" class="field-input">
            @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="businessName" class="field-label">Nome do negócio</label>
            <input id="businessName" wire:model="businessName" autocomplete="organization" required maxlength="120" class="field-input">
            @error('businessName') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="field-label">E-mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required class="field-input">
            @error('email') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="field-label">Senha</label>
            <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="field-input" aria-describedby="password-hint">
            <p id="password-hint" class="mt-1 text-xs text-stone-500">Use pelo menos 8 caracteres, com letras e números.</p>
            @error('password') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="field-label">Confirme a senha</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="field-input">
        </div>
        <button type="submit" class="primary-button w-full" wire:loading.attr="disabled">Criar minha conta <span wire:loading wire:target="register">…</span></button>
    </form>
    <p class="mt-6 text-center text-sm text-stone-500">Já tem uma conta? <a href="{{ route('login') }}" class="font-semibold text-teal-800 underline underline-offset-4">Entrar</a></p>
</div>
