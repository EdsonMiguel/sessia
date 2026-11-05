<?php

namespace App\Livewire\Contract;

use App\Models\ServiceContract;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ContractList extends Component
{
    use WithPagination;

    public string $type = '';

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('viewAny', ServiceContract::class);

        return view('livewire.contract.contract-list', ['contracts' => ServiceContract::with('customer')->when(isset(ServiceContract::TYPES[$this->type]), fn ($q) => $q->where('type', $this->type))->latest('id')->paginate(15)]);
    }
}
