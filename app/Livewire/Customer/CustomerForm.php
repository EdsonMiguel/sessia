<?php

namespace App\Livewire\Customer;

use App\Models\Customer;
use Illuminate\Support\Facades\Gate;
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
        } else {
            Gate::authorize('create', Customer::class);
        }
    }

    public function save(): void
    {
        $customer = $this->customerId === null ? new Customer : Customer::findOrFail($this->customerId);
        Gate::authorize($customer->exists ? 'update' : 'create', $customer->exists ? $customer : Customer::class);
        $this->name = trim($this->name);
        $this->phone = trim($this->phone);
        $this->email = mb_strtolower(trim($this->email));
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
        $customer->fill($data)->save();
        session()->flash('status', 'Cliente salva com sucesso.');
        $this->redirectRoute('customers.show', ['customerId' => $customer->id]);
    }

    public function render()
    {
        Gate::authorize('viewAny', Customer::class);

        return view('livewire.customer.customer-form');
    }
}
