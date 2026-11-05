<?php

namespace App\Livewire\Customer;

use App\Models\Customer;
use App\Modules\Customer\SaveCustomerAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CustomerForm extends Component
{
    #[Locked]
    public ?int $customerId = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $notes = '';

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

    private const ADDRESS_FIELDS = ['postal_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'reference'];

    public function mount(?int $customerId = null): void
    {
        $this->customerId = $customerId;
        if ($customerId !== null) {
            $customer = Customer::findOrFail($customerId);
            Gate::authorize('update', $customer);
            $this->name = $customer->name;
            $this->phone = $customer->phone;
            $this->email = $customer->email ?? '';
            $this->notes = $customer->notes ?? '';
            $address = $customer->addresses()->where('is_primary', true)->first();
            if ($address !== null) {
                $this->addressId = $address->id;
                foreach (self::ADDRESS_FIELDS as $field) {
                    $this->{$field} = $address->{$field} ?? '';
                }
            }
        } else {
            Gate::authorize('create', Customer::class);
        }
    }

    public function save(SaveCustomerAddress $saveAddress): void
    {
        $customer = $this->customerId === null ? new Customer : Customer::findOrFail($this->customerId);
        Gate::authorize($customer->exists ? 'update' : 'create', $customer->exists ? $customer : Customer::class);
        $this->name = trim($this->name);
        $this->phone = trim($this->phone);
        $this->email = mb_strtolower(trim($this->email));
        $hasAddress = $this->addressId !== null;
        foreach (self::ADDRESS_FIELDS as $field) {
            $this->{$field} = trim($this->{$field});
            $hasAddress = $hasAddress || $this->{$field} !== '';
        }
        $this->state = strtoupper($this->state);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s().-]{8,30}$/D', function (string $attribute, mixed $value, \Closure $fail) {
                $digits = strlen(preg_replace('/\D/', '', $value));
                if ($digits < 8 || $digits > 15) {
                    $fail('Informe um telefone com 8 a 15 dígitos.');
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['email'] = $data['email'] ?: null;
        $data['notes'] = $data['notes'] ?: null;
        $addressData = $hasAddress ? $this->validate([
            'postal_code' => ['required', 'regex:/^\d{5}-?\d{3}$/D'],
            'street' => ['required', 'string', 'max:160'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(AddressForm::STATES)],
            'reference' => ['nullable', 'string', 'max:255'],
        ]) : null;
        if ($addressData !== null) {
            $addressData['complement'] = $addressData['complement'] ?: null;
            $addressData['reference'] = $addressData['reference'] ?: null;
            $addressData['is_primary'] = true;
        }

        DB::transaction(function () use ($customer, $data, $addressData, $saveAddress) {
            $customer->fill($data)->save();
            if ($addressData !== null) {
                $saveAddress->handle($customer->id, $this->addressId, $addressData);
            }
        });
        session()->flash('status', 'Cliente salva com sucesso.');
        $this->redirectRoute('customers.show', ['customerId' => $customer->id]);
    }

    public function render()
    {
        Gate::authorize('viewAny', Customer::class);

        return view('livewire.customer.customer-form');
    }
}
