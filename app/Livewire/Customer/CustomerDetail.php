<?php

namespace App\Livewire\Customer;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Procedure;
use App\Models\Receivable;
use App\Models\ServiceContract;
use App\Modules\Catalog\DecimalAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CustomerDetail extends Component
{
    #[Locked]
    public int $customerId;

    public string $procedureId = '';

    public string $price = '';

    public function mount(int $customerId): void
    {
        $this->customerId = $customerId;
        $this->customer();
    }

    private function customer(): Customer
    {
        $customer = Customer::findOrFail($this->customerId);
        Gate::authorize('view', $customer);

        return $customer;
    }

    public function toggleActive(): void
    {
        $customer = $this->customer();
        Gate::authorize('update', $customer);
        $customer->update(['is_active' => ! $customer->is_active]);
        session()->flash('status', $customer->is_active ? 'Cliente reativada.' : 'Cliente desativada. O histórico foi preservado.');
    }

    public function savePrice(): void
    {
        $customer = $this->customer();
        Gate::authorize('update', $customer);
        $this->price = trim($this->price);
        $this->validate(['procedureId' => ['required', 'integer'], 'price' => DecimalAmount::rules()]);
        $procedure = Procedure::where('tenant_id', $customer->tenant_id)->find($this->procedureId);
        if (! $procedure) {
            $this->addError('procedureId', 'Selecione um procedimento do seu negócio.');

            return;
        }
        Gate::authorize('view', $procedure);

        DB::transaction(function () use ($customer, $procedure) {
            $locked = Customer::where('tenant_id', $customer->tenant_id)->lockForUpdate()->findOrFail($customer->id);
            $locked->procedurePrices()->updateOrCreate(
                ['procedure_id' => $procedure->id],
                ['price' => DecimalAmount::normalize($this->price)],
            );
        });
        $this->reset('procedureId', 'price');
        session()->flash('status', 'Preço personalizado salvo.');
    }

    public function removePrice(int $priceId): void
    {
        $customer = $this->customer();
        Gate::authorize('update', $customer);
        $customer->procedurePrices()->findOrFail($priceId)->delete();
        session()->flash('status', 'Preço personalizado removido. O preço padrão volta a ser sugerido.');
    }

    public function removeAddress(int $addressId): void
    {
        $customer = $this->customer();
        Gate::authorize('update', $customer);
        DB::transaction(function () use ($customer, $addressId) {
            $locked = Customer::where('tenant_id', $customer->tenant_id)->lockForUpdate()->findOrFail($customer->id);
            $address = $locked->addresses()->findOrFail($addressId);
            $address->update(['is_primary' => false]);
            $address->delete();
        });
        session()->flash('status', 'Endereço removido do cadastro.');
    }

    public function render()
    {
        $customer = $this->customer()->load([
            'addresses' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
            'procedurePrices.procedure',
        ]);
        $procedures = Procedure::orderBy('name')->get(['id', 'name', 'default_price', 'is_active']);

        $contracts = ServiceContract::where('customer_id', $customer->id)->with([
            'items' => fn ($q) => $q->withCount(['activeExecutions', 'reservations']),
            'periods' => fn ($q) => $q->latest('month')->limit(3),
            'periods.items' => fn ($q) => $q->withCount(['activeExecutions', 'reservations']),
            'periods.items.contractItem',
        ])->latest('id')->get();
        $appointments = Appointment::where('customer_id', $customer->id)->latest('starts_at')->limit(10)->get();
        $receivables = Receivable::where('customer_id', $customer->id)->with('installments.allocations.payment')->latest('id')->get();
        $pendingCents = $receivables->sum(fn ($r) => $r->installments->sum(fn ($i) => $i->remainingCents()));

        return view('livewire.customer.customer-detail', compact('customer', 'procedures', 'contracts', 'appointments', 'receivables', 'pendingCents'));
    }
}
