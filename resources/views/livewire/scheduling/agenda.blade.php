<div x-data="{}" x-on:period-blocked.window="$refs.blockDialog.close()">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Sua rotina, com espaço para cuidar</p><h1 class="page-title">Agenda</h1></div><div class="flex w-full flex-wrap gap-3 sm:w-auto"><button type="button" x-on:click="$refs.blockDialog.showModal()" aria-haspopup="dialog" aria-controls="block-dialog" class="min-h-12 w-full rounded-xl border border-teal-800 px-5 py-3 text-sm font-semibold text-teal-800 hover:bg-teal-50 sm:w-auto"><x-icon name="lock" /> <span>Bloquear um período</span></button><a href="{{ route('appointments.create') }}" class="primary-button"><x-icon name="plus" /> <span>Novo agendamento</span></a></div></div>
    <x-flash /><x-form.errors />
    <div class="my-6 grid max-w-xl gap-4 sm:grid-cols-2"><x-form.input name="date" label="Data de referência" type="date" wire:model.live="date" required /><div><label for="view" class="field-label">Visualização</label><x-form.select id="view" wire:model.live="view" class="field-input"><option value="day">Diária</option><option value="week">Calendário semanal</option><option value="month">Mensal</option></x-form.select></div></div>
    <p class="mb-5 text-sm text-stone-500">Horários em {{ str_replace('_', ' ', $tz) }}. O tempo de preparação e deslocamento também ocupa a agenda.</p>
    @php
        $weekdays = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        $statusColors = [
            'scheduled' => 'bg-sky-100 hover:bg-sky-200',
            'confirmed' => 'bg-violet-100 hover:bg-violet-200',
            'completed' => 'bg-emerald-100 hover:bg-emerald-200',
            'cancelled' => 'bg-rose-100 hover:bg-rose-200',
            'no_show' => 'bg-amber-100 hover:bg-amber-200',
        ];
    @endphp
    @if($view === 'week')
        @include('livewire.scheduling.agenda-week')
    @else
    <div @class(['grid gap-4', 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7' => $view === 'month'])>
        @foreach($days as $day)<section wire:key="day-{{ $day['date']->toDateString() }}" class="min-w-0 rounded-2xl border border-stone-200 bg-white p-4"><h2 class="font-semibold text-teal-900">{{ $day['date']->format('d/m/Y') }}</h2><p class="mt-1 text-xs text-stone-500">{{ $weekdays[$day['date']->dayOfWeek] }}</p>
            @foreach($day['appointments'] as $appointment)
                <a href="{{ route('appointments.show', $appointment->id) }}" class="mt-4 block rounded-xl p-3 {{ $statusColors[$appointment->status] }}">
                    <p class="text-xs font-semibold text-stone-800">{{ $appointment->starts_at->timezone($tz)->format('H:i') }}–{{ $appointment->ends_at->timezone($tz)->format('H:i') }}</p>
                    <h3 class="mt-2 break-words text-sm font-semibold">{{ $appointment->customer->name }}</h3>
                    <p class="mt-1 break-words text-xs text-stone-700">{{ $appointment->items->pluck('procedure_name')->implode(' · ') }}</p>
                    <p class="mt-2 text-xs font-medium text-stone-800">{{ \App\Models\Appointment::STATUSES[$appointment->status] }}</p>
                </a>
            @endforeach
            @foreach($day['blocks'] as $block)<div class="mt-4 rounded-xl bg-stone-100 p-3"><p class="text-xs font-semibold">Bloqueado · {{ $block->starts_at->timezone($tz)->format('d/m H:i') }}–{{ $block->ends_at->timezone($tz)->format('d/m H:i') }}</p><p class="mt-2 break-words text-sm">{{ $block->reason }}</p><button wire:click="unblock({{ $block->id }})" data-confirm="Remover este bloqueio?" wire:loading.attr="disabled" class="mt-2 min-h-11 text-xs font-medium text-stone-600 underline">Remover bloqueio</button></div>@endforeach
            @if($day['appointments']->isEmpty() && $day['blocks']->isEmpty())<p class="mt-5 text-xs text-stone-400">Sem compromissos</p>@endif
        </section>@endforeach
    </div>
    @endif
    <dialog id="block-dialog" x-ref="blockDialog" wire:ignore.self aria-labelledby="block-dialog-title" class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-2xl border border-stone-200 bg-white p-4 text-stone-800 shadow-xl backdrop:bg-stone-900/50 sm:p-6">
        <div class="flex items-center justify-between gap-4">
            <h2 id="block-dialog-title" class="text-lg font-semibold">Bloquear um período</h2>
            <button type="button" x-on:click="$refs.blockDialog.close()" aria-label="Fechar janela de bloqueio" class="min-h-11 min-w-11 rounded-lg text-2xl hover:bg-stone-100">×</button>
        </div>
        <p class="mt-2 text-sm text-stone-500">Reserve um horário sem atendimentos. Horários em {{ str_replace('_', ' ', $tz) }}.</p>
        <x-form.errors />
        <form wire:submit="block" class="mt-5 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2"><x-form.input name="blockStart" label="Início" type="datetime-local" wire:model="blockStart" required /><x-form.input name="blockEnd" label="Fim" type="datetime-local" wire:model="blockEnd" required /></div>
            <x-form.input name="blockReason" label="Motivo" wire:model="blockReason" required maxlength="255" />
            <div class="flex flex-wrap gap-3 sm:justify-end">
                <button type="button" x-on:click="$refs.blockDialog.close()" class="min-h-12 w-full rounded-xl border border-stone-300 px-5 text-sm font-medium sm:w-auto"><x-icon name="close" /> <span>Cancelar</span></button>
                <button class="primary-button" wire:loading.attr="disabled" wire:target="block"><x-icon name="lock" /> <span>Bloquear período</span></button>
            </div>
        </form>
    </dialog>
</div>
