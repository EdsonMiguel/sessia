<div>
    <p class="eyebrow">Seu espaço</p>
    <h1 class="page-title">Configurações do negócio</h1>
    <p class="mt-3 text-stone-500">Mantenha os dados do seu negócio em dia.</p>
    <form wire:submit="save" class="mt-8 max-w-2xl space-y-6 rounded-2xl border border-stone-200 bg-white p-4 sm:p-8">
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
        <section class="space-y-4 border-t border-stone-100 pt-6">
            <h2 class="flex items-center gap-2 font-semibold"><x-icon name="document" /> Catálogo público</h2>
            <x-form.input name="phone" label="Telefone / WhatsApp do negócio" wire:model="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="(11) 99999-1234" />
            <p class="text-xs text-stone-500">Informe o DDD. Para números de outros países, inclua + e o DDI. Este número será usado no contato pelo catálogo.</p>
            <x-form.input name="slug" label="Slug do catálogo" wire:model="slug" maxlength="80" placeholder="meu-espaco" autocapitalize="none" spellcheck="false" />
            <p class="text-xs text-stone-500">Use letras sem acento, números e hífens. Seu catálogo ficará em /catalogo/seu-slug, sem exigir login. Deixe vazio para desativar a página. Alterar o slug muda o link compartilhado.</p>
            @if($catalogUrl)
                <a href="{{ $catalogUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 break-all text-sm font-semibold text-teal-800"><x-icon name="arrow-right" /> {{ $catalogUrl }}</a>
            @endif
        </section>
        <section class="space-y-4 border-t border-stone-100 pt-6"><h2 class="font-semibold">Recebimentos por Pix</h2><p class="text-sm text-stone-500">Use a chave já cadastrada na conta bancária do seu negócio. A confirmação dos recebimentos será manual.</p><x-form.input name="pix_key" label="Chave Pix (opcional)" wire:model="pix_key" maxlength="140" /><x-form.input name="pix_receiver" label="Nome do recebedor" wire:model="pix_receiver" maxlength="120" /><x-form.input name="pix_city" label="Cidade do recebedor" wire:model="pix_city" maxlength="120" /></section>
        <p class="text-xs text-stone-500">Se a chave Pix for um telefone, informe o DDI e o DDD, por exemplo +5511999991234.</p>
        <button class="primary-button" wire:loading.attr="disabled">Salvar alterações <span wire:loading wire:target="save">…</span></button>
    </form>
</div>
