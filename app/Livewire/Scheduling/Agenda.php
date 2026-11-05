<?php

namespace App\Livewire\Scheduling;

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Modules\Platform\CurrentTenant;
use App\Modules\Scheduling\ScheduleAppointment;
use Carbon\CarbonImmutable;
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
            'month' => $date->startOfMonth(), 'week' => $date->startOfWeek(), default => $date
        };
        $until = match ($this->view) {
            'month' => $from->addMonth(), 'week' => $from->addWeek(), default => $from->addDay()
        };
        $appointments = Appointment::with('customer', 'items')->where('starts_at', '<', $until->utc())->where('occupied_until', '>', $from->utc())->orderBy('starts_at')->get();
        $blocks = ScheduleBlock::whereNull('cancelled_at')->where('starts_at', '<', $until->utc())->where('ends_at', '>', $from->utc())->get();
        $days = [];
        for ($day = $from; $day < $until; $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[] = ['date' => $day, 'appointments' => $appointments->filter(fn ($a) => $a->starts_at->timezone($tz) < $day->addDay() && $a->occupied_until->timezone($tz) > $day),
                'blocks' => $blocks->filter(fn ($b) => $b->starts_at->timezone($tz) < $day->addDay() && $b->ends_at->timezone($tz) > $day)];
        }

        return view('livewire.scheduling.agenda', compact('days', 'tz'));
    }
}
