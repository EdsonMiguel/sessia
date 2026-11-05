<div>
    <a href="{{ route('procedures.index') }}" class="text-sm font-medium text-teal-800">← Procedimentos</a>
    <h1 class="page-title">{{ $procedureId ? 'Editar procedimento' : 'Novo procedimento' }}</h1>
    <p class="mt-3 text-stone-500">Defina a duração e o preço de referência do serviço.</p>
    <form wire:submit="save" class="mt-8 max-w-2xl space-y-5 rounded-2xl border border-stone-200 bg-white p-4 sm:p-8">
        <x-form.input name="name" label="Nome do procedimento" wire:model="name" required maxlength="120" />
        <x-form.textarea name="description" label="Descrição (opcional)" wire:model="description" maxlength="5000" />
        <div class="grid gap-5 sm:grid-cols-2"><x-form.input name="duration_minutes" label="Duração estimada (minutos)" wire:model="duration_minutes" type="number" inputmode="numeric" min="1" max="65535" step="1" required /><x-form.input name="default_price" label="Preço padrão (R$)" wire:model="default_price" inputmode="decimal" placeholder="120,00" required /></div>
        <p class="text-sm text-stone-500">Use vírgula ou ponto para os centavos, sem separador de milhar. Você poderá definir preços personalizados no cadastro de cada cliente.</p>
        <div class="rounded-xl bg-teal-50 p-4">
            <label class="flex min-h-11 items-center gap-3 text-sm font-semibold text-teal-900"><input type="checkbox" wire:model="show_in_catalog" class="size-5 shrink-0 accent-teal-800"> Exibir no catálogo público</label>
            <p class="mt-2 text-xs text-stone-600">Publica nome, descrição, duração e preço padrão. O procedimento também precisa estar ativo. Revise a descrição antes de publicar.</p>
            @error('show_in_catalog')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap items-center gap-4"><button class="primary-button" wire:loading.attr="disabled"><x-icon name="save" /> <span>Salvar procedimento</span></button><a href="{{ route('procedures.index') }}" class="text-sm text-stone-600"><x-icon name="close" /> <span>Cancelar</span></a></div>
    </form>
</div>
