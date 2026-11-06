<div>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><p class="eyebrow">Seu catálogo</p><h1 class="page-title">Pacotes</h1><p class="mt-3 text-stone-500">Combinações prontas para oferecer e personalizar para cada cliente.</p></div>
        <a href="{{ route('packages.create') }}" class="primary-button shrink-0"><x-icon name="plus" /> Novo pacote</a>
    </div>
    @if(session('status'))<p role="status" class="mt-5 rounded-xl bg-teal-50 p-4 text-sm text-teal-900">{{ session('status') }}</p>@endif
    <div class="my-6 grid gap-4 sm:grid-cols-[1fr_12rem]">
        <x-form.input name="search" label="Buscar pacote" wire:model.live.debounce.300ms="search" placeholder="Nome do pacote" />
        <div><label for="status" class="field-label">Situação</label><x-form.select id="status" wire:model.live="status" class="field-input"><option value="active">Ativos</option><option value="inactive">Inativos</option><option value="all">Todos</option></x-form.select></div>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($packages as $package)
            @php($available = $package->items->isNotEmpty() && $package->items->every(fn ($item) => $item->procedure?->is_active))
            <article wire:key="package-{{ $package->id }}" class="flex min-w-0 flex-col rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
                <div class="flex items-start gap-3"><x-icon name="package" class="mt-1 shrink-0 text-teal-800" /><h2 class="break-words text-xl font-semibold">{{ $package->name }}</h2></div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs"><span class="rounded-full bg-stone-100 px-3 py-1">{{ $package->is_active ? 'Ativo' : 'Inativo' }}</span>@if($package->show_in_catalog)<span class="rounded-full bg-teal-50 px-3 py-1 text-teal-900">{{ $package->is_active && $available ? 'No catálogo público' : 'Publicação suspensa' }}</span>@endif</div>
                @if(!$available)<p class="mt-3 text-sm text-amber-800">Revise os itens: há procedimentos inativos ou indisponíveis.</p>@endif
                @if($package->description)<p class="mt-4 break-words text-sm text-stone-500">{{ $package->description }}</p>@endif
                <ul class="mt-4 space-y-2 text-sm text-stone-600">@foreach($package->items as $item)<li>{{ $item->quantity }} × {{ $item->procedure?->name ?? 'Procedimento indisponível' }}</li>@endforeach</ul>
                <p class="mt-auto pt-5 text-2xl font-semibold text-teal-900">{{ \App\Modules\Catalog\DecimalAmount::format($package->total) }}</p>
                <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-stone-100 pt-3">
                    <a href="{{ route('packages.edit', $package->id) }}" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-800"><x-icon name="edit" /> Editar</a>
                    <button type="button" wire:click="toggleActive({{ $package->id }})" wire:loading.attr="disabled" class="min-h-11 text-sm text-stone-600">{{ $package->is_active ? 'Desativar' : 'Reativar' }}</button>
                </div>
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-stone-300 p-8 text-center text-stone-500 md:col-span-2">Nenhum pacote encontrado. Cadastre uma combinação de procedimentos para começar.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $packages->links() }}</div>
</div>
