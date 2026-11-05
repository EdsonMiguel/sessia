<?php

namespace App\Livewire\Scheduling;

use App\Models\Appointment;
use App\Models\ContractItem;
use App\Models\Customer;
use App\Models\PlanPeriodItem;
use App\Models\Procedure;
use App\Modules\Platform\CurrentTenant;
use App\Modules\Scheduling\ScheduleAppointment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AppointmentForm extends Component
{
    #[Locked]
    public ?int $appointmentId = null;

    public string $customer_id = '';

    public string $address_id = '';

    public string $starts_at = '';

    public string $preparation_minutes = '0';

    public string $travel_minutes = '0';

    public string $notes = '';

    public array $items = [['procedure_id' => '', 'source' => '']];

    public function mount(?int $appointmentId = null): void
    {
        $this->appointmentId = $appointmentId;
        $tz = app(CurrentTenant::class)->get()->timezone;
        Gate::authorize('create', Appointment::class);
        $this->starts_at = now($tz)->addHour()->format('Y-m-d\TH:i');
        if ($appointmentId) {
            $a = Appointment::with('items')->findOrFail($appointmentId);
            Gate::authorize('update', $a);
            $this->customer_id = (string) $a->customer_id;
            $this->address_id = (string) ($a->address_snapshot['address_id'] ?? '');
            $this->starts_at = $a->starts_at->timezone($tz)->format('Y-m-d\TH:i');
            $this->preparation_minutes = (string) $a->preparation_minutes;
            $this->travel_minutes = (string) $a->travel_minutes;
            $this->notes = $a->notes ?? '';
            $this->items = $a->items->map(fn ($row) => ['procedure_id' => (string) $row->procedure_id,
                'source' => $row->plan_period_item_id ? 'period:'.$row->plan_period_item_id : ($row->contract_item_id ? 'contract:'.$row->contract_item_id : '')])->all();
        }
    }

    public function updatedCustomerId(): void
    {
        $this->address_id = '';
        $this->items = [['procedure_id' => '', 'source' => '']];
    }

    public function addItem(): void
    {
        if (count($this->items) < 30) {
            $this->items[] = ['procedure_id' => '', 'source' => ''];
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.source') && preg_match('/^(contract|period):(\d+)$/D', (string) $value, $match)) {
            $item = $match[1] === 'period' ? PlanPeriodItem::findOrFail($match[2])->contractItem : ContractItem::findOrFail($match[2]);
            abort_unless($item->contract->customer_id === (int) $this->customer_id, 404);
            $this->items[explode('.', $key)[0]]['procedure_id'] = (string) $item->procedure_id;
        }
    }

    public function save(ScheduleAppointment $action): void
    {
        $rows = [];
        foreach ($this->items as $index => $row) {
            $data = ['procedure_id' => $row['procedure_id'] ?? ''];
            if (! empty($row['source'])) {
                if (! preg_match('/^(contract|period):(\d+)$/D', $row['source'], $match)) {
                    throw ValidationException::withMessages(["items.$index" => 'Selecione uma sessão válida.']);
                }
                if ($match[1] === 'period') {
                    $periodItem = PlanPeriodItem::findOrFail($match[2]);
                    $data += ['plan_period_item_id' => $periodItem->id, 'contract_item_id' => $periodItem->contract_item_id];
                } else {
                    $data['contract_item_id'] = (int) $match[2];
                }
            }
            $rows[] = $data;
        }
        $a = $action->handle(['customer_id' => $this->customer_id, 'address_id' => $this->address_id, 'starts_at' => $this->starts_at,
            'preparation_minutes' => $this->preparation_minutes, 'travel_minutes' => $this->travel_minutes, 'notes' => $this->notes, 'items' => $rows], $this->appointmentId);
        $this->redirectRoute('appointments.show', $a->id);
    }

    public function render()
    {
        Gate::authorize('viewAny', Appointment::class);
        $customer = $this->customer_id ? Customer::find($this->customer_id) : null;

        return view('livewire.scheduling.appointment-form', [
            'customers' => Customer::orderBy('name')->get(), 'addresses' => $customer?->addresses()->get() ?? collect(),
            'procedures' => Procedure::orderBy('name')->get(),
            'contractItems' => $customer ? ContractItem::with('contract')->withCount(['activeExecutions', 'reservations'])->whereHas('contract', fn ($q) => $q->where('customer_id', $customer->id)->where('status', 'active')->where('type', '!=', 'plan'))->get() : collect(),
            'periodItems' => $customer ? PlanPeriodItem::with('contractItem', 'period')->withCount(['activeExecutions', 'reservations'])->whereHas('period.contract', fn ($q) => $q->where('customer_id', $customer->id)->where('status', 'active'))->get() : collect(),
        ]);
    }
}
