<div>
    <h2 class="text-2xl font-semibold tracking-tight">Bem-vinda de volta</h2>
    <p class="mt-2 text-sm text-stone-500">Entre para acessar seu negócio.</p>
    <form wire:submit="login" class="mt-8 space-y-5">
        <div>
            <label for="email" class="field-label">E-mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required class="field-input" aria-describedby="email-error">
            @error('email') <p id="email-error" class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="field-label">Senha</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required class="field-input" aria-describedby="password-error">
            @error('password') <p id="password-error" class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" wire:model="remember" class="accent-teal-800"> Manter conectado neste dispositivo</label>
        <button type="submit" class="primary-button w-full" wire:loading.attr="disabled">Entrar <span wire:loading wire:target="login">…</span></button>
    </form>
    <p class="mt-6 text-center text-sm text-stone-500">Começando por aqui? <a href="{{ route('register') }}" class="font-semibold text-teal-800 underline underline-offset-4">Criar minha conta</a></p>
</div>
