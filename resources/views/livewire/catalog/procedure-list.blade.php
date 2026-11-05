<div>
    <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">O que você faz de melhor</p><h1 class="page-title">Procedimentos</h1></div><a href="{{ route('procedures.create') }}" class="primary-button">+ Novo procedimento</a></div>
    <x-flash />
    <div class="my-7 grid gap-4 sm:grid-cols-[1fr_12rem]"><x-form.input name="search" label="Buscar procedimento" type="search" wire:model.live.debounce.300ms="search" maxlength="120" placeholder="Nome do procedimento" /><div><label for="status" class="field-label">Situação</label><select id="status" wire:model.live="status" class="field-input"><option value="active">Ativos</option><option value="inactive">Inativos</option><option value="all">Todos</option></select></div></div>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($procedures as $procedure)
            <article wire:key="procedure-{{ $procedure->id }}" class="min-w-0 rounded-2xl border border-stone-200 bg-white p-6">
                <div class="flex flex-wrap items-start justify-between gap-2"><h2 class="break-words text-lg font-semibold">{{ $procedure->name }}</h2><span class="rounded-full bg-stone-100 px-3 py-1 text-xs">{{ $procedure->is_active ? 'Ativo' : 'Inativo' }}</span></div>
                @if($procedure->description)<p class="mt-3 whitespace-pre-line break-words text-sm text-stone-500">{{ $procedure->description }}</p>@endif
                <p class="mt-5 text-xl font-semibold text-teal-900">{{ \App\Modules\Catalog\DecimalAmount::format($procedure->default_price) }}</p><p class="mt-1 text-sm text-stone-500">Preço padrão · {{ $procedure->duration_minutes }} min</p>
                <div class="mt-4 flex flex-wrap justify-between gap-4 text-sm"><a href="{{ route('procedures.edit', $procedure->id) }}" class="min-h-11 py-3 font-semibold text-teal-800">Editar procedimento</a><button wire:click="toggleActive({{ $procedure->id }})" wire:confirm="{{ $procedure->is_active ? 'Desativar este procedimento? Seus dados serão preservados.' : 'Reativar este procedimento?' }}" wire:loading.attr="disabled" class="min-h-11 py-3 text-stone-600">{{ $procedure->is_active ? 'Desativar' : 'Reativar' }}</button></div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-stone-300 p-10 text-center md:col-span-2"><h2 class="font-semibold">Nenhum procedimento encontrado</h2><p class="mt-2 text-sm text-stone-500">Adicione seus serviços ou ajuste os filtros da busca.</p></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $procedures->links() }}</div>
</div>
