<div>
    <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Acordos claros, cuidado contínuo</p><h1 class="page-title">Contratações</h1></div><a href="{{ route('contracts.create') }}" class="primary-button">+ Nova contratação</a></div>
    <x-flash />
    <div class="my-6 max-w-sm"><label for="type" class="field-label">Modalidade</label><select id="type" wire:model.live="type" class="field-input"><option value="">Todas</option>@foreach(\App\Models\ServiceContract::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
    <div class="space-y-3">@forelse($contracts as $contract)
        <a wire:key="contract-{{ $contract->id }}" href="{{ route('contracts.show', $contract->id) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-6 hover:border-teal-600"><div><p class="text-xs text-teal-700">{{ \App\Models\ServiceContract::TYPES[$contract->type] }} #{{ $contract->id }}</p><h2 class="mt-2 font-semibold">{{ $contract->customer->name }}</h2><p class="mt-1 text-sm text-stone-500">{{ \App\Models\ServiceContract::STATUSES[$contract->status] }}</p></div><p class="font-semibold">{{ \App\Modules\Catalog\DecimalAmount::format($contract->total) }}{{ $contract->type === 'plan' ? ' / mês' : '' }} →</p></a>
    @empty <p class="rounded-2xl border border-dashed border-stone-300 p-8 text-center text-stone-500">Nenhuma contratação cadastrada. Registre um atendimento avulso, plano ou pacote.</p>@endforelse</div>
    <div class="mt-6">{{ $contracts->links() }}</div>
</div>
