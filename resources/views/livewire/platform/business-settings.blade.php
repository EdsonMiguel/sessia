<div>
    <p class="eyebrow">Seu espaço</p>
    <h1 class="page-title">Configurações do negócio</h1>
    <p class="mt-3 text-stone-500">Mantenha os dados do seu negócio em dia.</p>
    <form wire:submit="save" class="mt-8 max-w-2xl space-y-6 rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
        @if(session('status')) <p role="status" class="rounded-lg bg-teal-50 p-4 text-sm text-teal-900">{{ session('status') }}</p> @endif
        <div>
            <label for="name" class="field-label">Nome do negócio</label>
            <input id="name" wire:model="name" required maxlength="120" class="field-input">
            @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="timezone" class="field-label">Fuso horário</label>
            <select id="timezone" wire:model="timezone" required class="field-input">
                @foreach(DateTimeZone::listIdentifiers() as $zone)
                    <option value="{{ $zone }}">{{ str_replace('_', ' ', $zone) }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-sm text-stone-500">Usado para apresentar os horários do seu negócio.</p>
            @error('timezone') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </div>
        <section class="space-y-4 border-t border-stone-100 pt-6"><h2 class="font-semibold">Recebimentos por Pix</h2><p class="text-sm text-stone-500">Use a chave já cadastrada na conta bancária do seu negócio. A confirmação dos recebimentos será manual.</p><x-form.input name="pix_key" label="Chave Pix (opcional)" wire:model="pix_key" maxlength="140" /><x-form.input name="pix_receiver" label="Nome do recebedor" wire:model="pix_receiver" maxlength="120" /><x-form.input name="pix_city" label="Cidade do recebedor" wire:model="pix_city" maxlength="120" /></section>
        <button class="primary-button" wire:loading.attr="disabled">Salvar alterações <span wire:loading wire:target="save">…</span></button>
    </form>
</div>
