@php
    $weekStart = $days[0]['date'];
    $today = now($tz)->toDateString();
@endphp
<section aria-labelledby="agenda-week-title">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 id="agenda-week-title" class="text-sm font-semibold text-stone-700">
            Semana de {{ $weekStart->format('d/m/Y') }} a {{ $days[6]['date']->format('d/m/Y') }}
        </h2>
        <div class="flex gap-2">
            <button type="button" class="min-h-11 min-w-11 rounded-lg border border-stone-200 bg-white text-teal-800 hover:bg-teal-50" aria-label="Semana anterior" wire:click="$set('date', '{{ $weekStart->subWeek()->toDateString() }}')"><x-icon name="arrow-left" /></button>
            <button type="button" class="min-h-11 rounded-lg border border-stone-200 bg-white px-3 text-sm font-semibold text-teal-800 hover:bg-teal-50" wire:click="$set('date', '{{ $today }}')">Hoje</button>
            <button type="button" class="min-h-11 min-w-11 rounded-lg border border-stone-200 bg-white text-teal-800 hover:bg-teal-50" aria-label="Próxima semana" wire:click="$set('date', '{{ $weekStart->addWeek()->toDateString() }}')"><x-icon name="arrow-right" /></button>
        </div>
    </div>
    <div wire:key="calendar-week-{{ $date }}" x-data="{}"
        x-init="$el.scrollTop = $refs.firstHour.offsetTop; $el.scrollLeft = $refs.referenceDay.offsetLeft - $refs.timeAxis.offsetWidth"
        tabindex="0" role="region" aria-label="Grade de horários da semana"
        class="max-h-[65dvh] overflow-auto rounded-2xl border border-stone-200 bg-white focus-visible:outline-2 focus-visible:outline-teal-700">
        <div class="agenda-week">
            <div class="agenda-week-grid sticky top-0 z-20 border-b border-stone-200 bg-white text-center">
                <div x-ref="timeAxis" class="sticky left-0 z-10 flex items-center justify-center border-r border-stone-200 bg-white text-xs text-stone-500">Hora</div>
                @foreach($days as $day)
                    @php
                        $dayKey = $day['date']->toDateString();
                    @endphp
                    <div @if($dayKey === $date) x-ref="referenceDay" @endif @if($dayKey === $today) aria-current="date" @endif
                        @class(['border-r border-stone-200 px-2 py-2 last:border-r-0', 'bg-teal-50' => $dayKey === $today])>
                        <span class="block text-xs font-medium text-stone-600">{{ $weekdays[$day['date']->dayOfWeek] }}</span>
                        <span class="block text-lg font-semibold text-teal-900">{{ $day['date']->format('d/m') }}</span>
                    </div>
                @endforeach
            </div>
            <div class="agenda-week-grid">
                <div class="sticky left-0 z-10 border-r border-stone-200 bg-white" aria-hidden="true">
                    @for($hour = 0; $hour < 24; $hour++)
                        <div @if($hour === $firstHour) x-ref="firstHour" @endif class="agenda-week-hour border-b border-stone-200 pt-1 text-center text-xs text-stone-600">{{ sprintf('%02d:00', $hour) }}</div>
                    @endfor
                </div>
                @foreach($days as $day)
                    <div wire:key="calendar-day-{{ $day['date']->toDateString() }}" class="agenda-week-day relative border-r border-stone-200 last:border-r-0" role="group" aria-label="{{ $weekdays[$day['date']->dayOfWeek] }}, {{ $day['date']->format('d/m/Y') }}">
                        @foreach($day['events'] as $event)
                            @php
                                $record = $event['record'];
                                $hours = $record->starts_at->timezone($tz)->format('H:i').'–'.$record->ends_at->timezone($tz)->format('H:i');
                                $position = "--event-start: {$event['start']}; --event-end: {$event['end']}; --event-lane: {$event['lane']}; --event-lanes: {$event['lanes']}";
                            @endphp
                            @if($record instanceof \App\Models\Appointment)
                                @php
                                    $status = \App\Models\Appointment::STATUSES[$record->status];
                                    $details = $hours.' · '.$record->customer->name.' · '.$record->items->pluck('procedure_name')->implode(' · ').' · '.$status;
                                @endphp
                                <a wire:key="week-appointment-{{ $day['date']->toDateString() }}-{{ $record->id }}" href="{{ route('appointments.show', $record->id) }}"
                                    style="{{ $position }}" title="{{ $details }}" aria-label="{{ $details }}"
                                    class="agenda-week-event rounded-md px-1.5 py-0.5 text-xs text-stone-900 {{ $statusColors[$record->status] }}">
                                    <span class="block truncate font-semibold">{{ $record->customer->name }}</span>
                                    <span class="block truncate">{{ $hours }} · {{ $status }}</span>
                                    <span class="block truncate">{{ $record->items->pluck('procedure_name')->implode(' · ') }}</span>
                                </a>
                            @else
                                <button type="button" wire:key="week-block-{{ $day['date']->toDateString() }}-{{ $record->id }}" wire:click="unblock({{ $record->id }})"
                                    data-confirm="Remover este bloqueio?" wire:loading.attr="disabled" wire:target="unblock"
                                    style="{{ $position }}" title="Bloqueado · {{ $hours }} · {{ $record->reason }}" aria-label="Remover bloqueio de {{ $hours }}. {{ $record->reason }}"
                                    class="agenda-week-event rounded-md bg-stone-200 px-1.5 py-0.5 text-left text-xs text-stone-800 hover:bg-stone-300">
                                    <span class="block truncate font-semibold">Bloqueado · {{ $hours }}</span>
                                    <span class="block truncate">{{ $record->reason }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
