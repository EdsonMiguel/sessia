<?php

namespace App\Livewire\Customer;

use App\Models\Customer;
use App\Modules\Customer\SaveCustomerAddress;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AddressForm extends Component
{
    #[Locked]
    public int $customerId;

    #[Locked]
    public ?int $addressId = null;

    public string $postal_code = '';

    public string $street = '';

    public string $number = '';

    public string $complement = '';

    public string $neighborhood = '';

    public string $city = '';

    public string $state = '';

    public string $reference = '';

    public bool $is_primary = true;

    public const STATES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public function mount(int $customerId, ?int $addressId = null): void
    {
        $this->customerId = $customerId;
        $this->addressId = $addressId;
        $customer = Customer::findOrFail($customerId);
        Gate::authorize('update', $customer);
        if ($addressId !== null) {
            $address = $customer->addresses()->findOrFail($addressId);
            foreach (['postal_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'reference'] as $field) {
                $this->{$field} = $address->{$field} ?? '';
            }
            $this->is_primary = $address->is_primary;
        } else {
            $this->is_primary = ! $customer->addresses()->exists();
        }
    }

    public function save(SaveCustomerAddress $action): void
    {
        Gate::authorize('update', Customer::findOrFail($this->customerId));
        $this->state = strtoupper(trim($this->state));
        $data = $this->validate([
            'postal_code' => ['required', 'regex:/^\d{5}-?\d{3}$/D'],
            'street' => ['required', 'string', 'max:160'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(self::STATES)],
            'reference' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['boolean'],
        ]);
        foreach (['complement', 'reference'] as $field) {
            $data[$field] = $data[$field] ?: null;
        }
        $action->handle($this->customerId, $this->addressId, $data);
        session()->flash('status', 'Endereço salvo.');
        $this->redirectRoute('customers.show', ['customerId' => $this->customerId]);
    }

    public function render()
    {
        $customer = Customer::findOrFail($this->customerId);
        Gate::authorize('update', $customer);

        return view('livewire.customer.address-form', compact('customer'));
    }
}
