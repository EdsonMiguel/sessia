@props(['address'])

@php
    // Address-only hints may need confirmation in Uber; never invent coordinates.
    $uberUrl = 'https://m.uber.com/looking?'.http_build_query([
        'action' => 'setPickup',
        'pickup' => 'my_location',
        'dropoff' => ['formatted_address' => $address, 'nickname' => 'Local de atendimento'],
    ], '', '&', PHP_QUERY_RFC3986);
@endphp

<div x-data="{ copied: false, manualCopy: false }" class="mt-3 space-y-2">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ $uberUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-stone-900 px-5 py-3 text-sm font-semibold text-white hover:bg-stone-700 sm:w-auto">
            <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m5 8 2-4h10l2 4M5 8h14l2 4v6H3v-6l2-4Zm0 10v2m14-2v2M7 13h.01M17 13h.01" /></svg>
            Ir de Uber ↗
        </a>
        <button type="button" class="min-h-11 px-2 text-sm font-semibold text-teal-800"
            x-on:click="copied = false; manualCopy = false; if (navigator.clipboard &amp;&amp; window.isSecureContext) { navigator.clipboard.writeText(@js($address)).then(() => copied = true).catch(() => { manualCopy = true; $nextTick(() => $refs.copyAddress.select()) }); } else { manualCopy = true; $nextTick(() => $refs.copyAddress.select()); }">
            Copiar endereço
        </button>
    </div>
    <p class="text-xs text-stone-500">Confira o embarque e o destino no Uber antes de solicitar. Se o destino não aparecer, copie e cole o endereço.</p>
    <p x-cloak x-show="copied" role="status" class="text-sm text-teal-800">Endereço copiado.</p>
    <div x-cloak x-show="manualCopy">
        <label class="field-label">Selecione e copie o endereço
            <input x-ref="copyAddress" type="text" readonly value="{{ $address }}" class="field-input mt-2" x-on:focus="$el.select()">
        </label>
    </div>
</div>
