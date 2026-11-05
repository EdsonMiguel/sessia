<?php

namespace App\Livewire\Scheduling;

use App\Models\Appointment;
use App\Modules\Attendance\CompleteAttendance;
use App\Modules\Scheduling\ScheduleAppointment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AppointmentDetail extends Component
{
    #[Locked]
    public int $appointmentId;

    public string $reason = '';

    public function mount(int $appointmentId): void
    {
        $this->appointmentId = $appointmentId;
        $this->appointment();
    }

    private function appointment(): Appointment
    {
        $a = Appointment::findOrFail($this->appointmentId);
        Gate::authorize('view', $a);

        return $a;
    }

    public function changeState(string $status): void
    {
        app(ScheduleAppointment::class)->changeStatus($this->appointment()->id, $status, $this->reason ?: null);
        session()->flash('status', 'Situação atualizada.');
    }

    public function complete(): void
    {
        app(CompleteAttendance::class)->handle($this->appointment()->id);
        session()->flash('status', 'Execução registrada. O pagamento continua independente.');
    }

    public function reverse(): void
    {
        app(CompleteAttendance::class)->reverse($this->appointment()->id, $this->reason);
        session()->flash('status', 'Execução estornada e saldo restaurado. Revise as cobranças existentes; elas foram preservadas.');
    }

    public function render()
    {
        $appointment = $this->appointment()->load('customer', 'items.contractItem.contract', 'items.execution', 'events.user');

        return view('livewire.scheduling.appointment-detail', compact('appointment'));
    }
}
