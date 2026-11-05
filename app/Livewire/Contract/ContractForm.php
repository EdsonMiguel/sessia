<?php

namespace App\Livewire\Contract;

use App\Models\Customer;
use App\Models\Procedure;
use App\Models\ServiceContract;
use App\Modules\Catalog\SuggestProcedurePrice;
use App\Modules\Contract\CreateContract;
use App\Modules\Platform\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ContractForm extends Component
{
    public string $customer_id = '';

    public string $type = 'single';

    public string $total = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $weekday = '2';

    public string $preferred_time = '09:00';

    public string $due_day = '10';

    public string $address_id = '';

    public string $notes = '';

    public array $items = [['procedure_id' => '', 'quantity' => '1', 'unit_price' => '']];

    public function mount(): void
    {
        Gate::authorize('create', ServiceContract::class);
        $this->starts_on = now(app(CurrentTenant::class)->get()->timezone)->toDateString();
    }

    public function addItem(): void
    {
        if (count($this->items) < 30) {
            $this->items[] = ['procedure_id' => '', 'quantity' => '1', 'unit_price' => ''];
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.procedure_id') && $this->customer_id && ctype_digit((string) $value)) {
            $index = explode('.', $key)[0];
            $this->items[$index]['unit_price'] = app(SuggestProcedurePrice::class)->handle((int) $this->customer_id, (int) $value);
        }
    }

    public function updatedCustomerId(): void
    {
        $this->address_id = '';
        foreach ($this->items as $index => $item) {
            if ($item['procedure_id'] && $this->customer_id) {
                $this->items[$index]['unit_price'] = app(SuggestProcedurePrice::class)->handle((int) $this->customer_id, (int) $item['procedure_id']);
            }
        }
    }

    public function save(CreateContract $action): void
    {
        Gate::authorize('create', ServiceContract::class);
        $contract = $action->handle([
            'customer_id' => $this->customer_id, 'type' => $this->type, 'total' => trim($this->total),
            'starts_on' => $this->starts_on, 'ends_on' => $this->ends_on ?: null, 'notes' => $this->notes,
            'weekday' => $this->type === 'plan' ? $this->weekday : null,
            'preferred_time' => $this->type === 'plan' ? $this->preferred_time : null,
            'due_day' => $this->type === 'plan' ? $this->due_day : null,
            'address_id' => $this->type === 'plan' ? $this->address_id : null, 'items' => $this->items,
        ]);
        session()->flash('status', 'Contratação registrada. Agendamento e recebimento são controlados separadamente.');
        $this->redirectRoute('contracts.show', $contract->id);
    }

    public function render()
    {
        Gate::authorize('viewAny', ServiceContract::class);

        return view('livewire.contract.contract-form', [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'procedures' => Procedure::where('is_active', true)->orderBy('name')->get(),
            'addresses' => $this->customer_id ? Customer::find($this->customer_id)?->addresses()->get() ?? collect() : collect(),
        ]);
    }
}
