<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="eyebrow">Cada cliente, um cuidado</p><h1 class="page-title">Clientes</h1></div>
        <a href="{{ route('customers.create') }}" class="primary-button"><x-icon name="plus" /> <span>Nova cliente</span></a>
    </div>
    <x-flash />
    <div class="my-7 grid gap-4 sm:grid-cols-[1fr_12rem]">
        <x-form.input name="search" label="Buscar por nome, telefone ou e-mail" wire:model.live.debounce.300ms="search" type="search" maxlength="120" placeholder="Quem você procura?" />
        <div><label for="status" class="field-label">Situação</label><select id="status" wire:model.live="status" class="field-input"><option value="active">Ativas</option><option value="inactive">Inativas</option><option value="all">Todas</option></select></div>
    </div>
    <div class="space-y-3">
        @forelse($customers as $customer)
            <a wire:key="customer-{{ $customer->id }}" href="{{ route('customers.show', $customer->id) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-teal-500">
                <div class="min-w-0"><h2 class="break-words font-semibold">{{ $customer->name }}</h2><p class="mt-1 text-sm text-stone-500">{{ $customer->phone }}</p></div>
                <div class="flex items-center gap-4"><span class="rounded-full bg-stone-100 px-3 py-1 text-xs">{{ $customer->is_active ? 'Ativa' : 'Inativa' }}</span><span class="text-teal-800" aria-hidden="true">→</span></div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-stone-300 p-10 text-center"><h2 class="font-semibold">Nenhuma cliente encontrada</h2><p class="mt-2 text-sm text-stone-500">Cadastre sua primeira cliente ou ajuste a busca e os filtros.</p></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $customers->links() }}</div>
</div>
