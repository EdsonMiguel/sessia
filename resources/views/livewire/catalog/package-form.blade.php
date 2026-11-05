<div>
    <a href="{{ route('packages.index') }}" class="inline-flex min-h-11 items-center gap-2 text-sm text-teal-800"><x-icon name="arrow-left" /> Pacotes</a>
    <h1 class="page-title">{{ $packageId ? 'Editar pacote' : 'Novo pacote' }}</h1>
    <p class="mt-3 text-stone-500">Combine procedimentos e quantidades para sugerir este pacote nas contratações.</p>
    <x-form.errors />
    <form wire:submit="save" class="mt-7 max-w-3xl space-y-6 rounded-2xl border border-stone-200 bg-white p-4 sm:p-8">
        <x-form.input name="name" label="Nome do pacote" wire:model="name" maxlength="120" required />
        <x-form.textarea name="description" label="Descrição (opcional)" wire:model="description" maxlength="5000" />
        <section class="space-y-4" aria-labelledby="package-items-title">
            <h2 id="package-items-title" class="font-semibold">Procedimentos incluídos</h2>
            @foreach($items as $index => $item)
                <div wire:key="package-item-{{ $index }}" class="space-y-4 rounded-xl bg-stone-50 p-4">
                    <div>
                        <label for="procedure-{{ $index }}" class="field-label">Procedimento</label>
                        <select id="procedure-{{ $index }}" wire:model="items.{{ $index }}.procedure_id" required class="field-input">
                            <option value="">Selecione</option>
                            @foreach($procedures as $procedure)<option value="{{ $procedure->id }}" @disabled(!$procedure->is_active)>{{ $procedure->name }}{{ $procedure->is_active ? '' : ' (inativo — substitua este item)' }}</option>@endforeach
                        </select>
                    </div>
                    <x-form.input name="quantity-{{ $index }}" label="Quantidade de sessões" type="number" min="1" max="65535" wire:model="items.{{ $index }}.quantity" required />
                    <button type="button" wire:click="removeItem({{ $index }})" class="inline-flex min-h-11 items-center gap-2 text-sm text-stone-600"><x-icon name="trash" /> Remover item</button>
                </div>
            @endforeach
            <button type="button" wire:click="addItem" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-800"><x-icon name="plus" /> Adicionar procedimento</button>
        </section>
        <x-form.input name="total" label="Valor total do pacote (R$)" inputmode="decimal" wire:model="total" required placeholder="0,00" />
        <p class="text-sm text-stone-500">O total pode ser diferente da soma dos preços das sessões. Na contratação, você poderá personalizar os itens e negociar outro valor.</p>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 p-4">
            <input type="checkbox" wire:model="show_in_catalog" class="mt-1 size-5 shrink-0 accent-teal-800">
            <span><span class="block font-medium">Exibir no catálogo público</span><span class="mt-1 block text-sm text-stone-500">Publica nome, descrição, procedimentos incluídos, quantidades e valor total. O pacote só aparece enquanto ele e todos os seus procedimentos estiverem ativos.</span></span>
        </label>
        <button class="primary-button" wire:loading.attr="disabled"><x-icon name="save" /> Salvar pacote</button>
    </form>
</div>
