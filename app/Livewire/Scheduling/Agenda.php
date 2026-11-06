<?php

namespace App\Livewire\Scheduling;

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Modules\Platform\CurrentTenant;
use App\Modules\Scheduling\ScheduleAppointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Agenda extends Component
{
    public string $date = '';

    public string $view = 'week';

    public string $blockStart = '';

    public string $blockEnd = '';

    public string $blockReason = '';

    public function mount(): void
    {
        $this->date = now(app(CurrentTenant::class)->get()->timezone)->toDateString();
    }

    public function block(): void
    {
        app(ScheduleAppointment::class)->block($this->blockStart, $this->blockEnd, $this->blockReason);
        $this->reset('blockStart', 'blockEnd', 'blockReason');
        session()->flash('status', 'Período bloqueado.');
        $this->dispatch('period-blocked');
    }

    public function unblock(int $id): void
    {
        app(ScheduleAppointment::class)->unblock($id);
        session()->flash('status', 'Bloqueio removido. O registro foi preservado.');
    }

    public function render()
    {
        Gate::authorize('viewAny', Appointment::class);
        $this->validate(['date' => ['required', 'date_format:Y-m-d'], 'view' => ['in:day,week,month']]);
        $tz = app(CurrentTenant::class)->get()->timezone;
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $this->date, $tz);
        $from = match ($this->view) {
            'month' => $date->startOfMonth(), 'week' => $date->startOfWeek(CarbonImmutable::MONDAY), default => $date
        };
        $until = match ($this->view) {
            'month' => $from->addMonth(), 'week' => $from->addWeek(), default => $from->addDay()
        };
        $appointments = Appointment::with('customer', 'items')->where('starts_at', '<', $until->utc())->where('occupied_until', '>', $from->utc())->orderBy('starts_at')->get();
        $blocks = ScheduleBlock::whereNull('cancelled_at')->where('starts_at', '<', $until->utc())->where('ends_at', '>', $from->utc())->get();
        $days = [];
        for ($day = $from; $day < $until; $day = $day->addDay()) {
            $days[] = ['date' => $day, 'appointments' => $appointments->filter(fn ($a) => $a->starts_at->timezone($tz) < $day->addDay() && $a->occupied_until->timezone($tz) > $day),
                'blocks' => $blocks->filter(fn ($b) => $b->starts_at->timezone($tz) < $day->addDay() && $b->ends_at->timezone($tz) > $day)];
        }

        $firstHour = 8;
        if ($this->view === 'week') {
            foreach ($days as &$day) {
                $day['events'] = $this->weekEvents($day['date'], $day['appointments']->concat($day['blocks']));
                if ($day['events'] !== []) {
                    $firstHour = min($firstHour, intdiv($day['events'][0]['start'], 60));
                }
            }
            unset($day);
        }

        return view('livewire.scheduling.agenda', compact('days', 'tz', 'firstHour'));
    }

    private function weekEvents(CarbonImmutable $day, Collection $records): array
    {
        $tomorrow = $day->addDay();
        $events = [];
        foreach ($records as $record) {
            $start = $record->starts_at->timezone($day->timezone);
            $end = $record->ends_at->timezone($day->timezone);
            if ($end <= $day || $start >= $tomorrow) {
                continue;
            }

            $startMinute = $start < $day ? 0 : $start->hour * 60 + $start->minute;
            $endMinute = $end >= $tomorrow ? 1440 : $end->hour * 60 + $end->minute;
            $events[] = ['record' => $record, 'start' => $startMinute, 'end' => $endMinute];
        }

        usort($events, fn ($a, $b) => [$a['start'], -$a['end']] <=> [$b['start'], -$b['end']]);

        $groups = [];
        $laneEnds = [];
        $group = 0;
        foreach ($events as $event) {
            if ($laneEnds !== [] && $event['start'] >= max($laneEnds)) {
                $group++;
                $laneEnds = [];
            }

            $lane = 0;
            while (($laneEnds[$lane] ?? 0) > $event['start']) {
                $lane++;
            }

            // Give short events enough height for a label and keep that space clear of adjacent events.
            $event['end'] = min(1440, max($event['end'], $event['start'] + 20));
            $laneEnds[$lane] = $event['end'];
            $groups[$group][] = $event + ['lane' => $lane];
        }

        $positioned = [];
        foreach ($groups as $events) {
            $laneCount = max(array_column($events, 'lane')) + 1;
            foreach ($events as $event) {
                $positioned[] = $event + ['lanes' => $laneCount];
            }
        }

        return $positioned;
    }
}
