<?php

namespace App\Livewire\Customer;

use App\Models\Customer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class CustomerList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'active';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('viewAny', Customer::class);
        $customers = Customer::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_substr($this->search, 0, 120).'%';
                $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($this->status !== 'all', fn ($query) => $query->where('is_active', $this->status !== 'inactive'))
            ->orderBy('name')->orderBy('id')->paginate(15);

        return view('livewire.customer.customer-list', compact('customers'));
    }
}
