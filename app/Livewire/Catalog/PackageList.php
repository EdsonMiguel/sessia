<?php

namespace App\Livewire\Catalog;

use App\Models\ProcedurePackage;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class PackageList extends Component
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

    public function toggleActive(int $id): void
    {
        $package = ProcedurePackage::findOrFail($id);
        Gate::authorize('update', $package);
        $package->update(['is_active' => ! $package->is_active]);
        session()->flash('status', 'Situação do pacote atualizada.');
    }

    public function render()
    {
        Gate::authorize('viewAny', ProcedurePackage::class);

        return view('livewire.catalog.package-list', [
            'packages' => ProcedurePackage::with('items.procedure')
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->status !== 'all', fn ($q) => $q->where('is_active', $this->status === 'active'))
                ->orderBy('name')->orderBy('id')->paginate(12),
        ]);
    }
}
