<?php

namespace App\Livewire\Catalog;

use App\Models\Procedure;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ProcedureList extends Component
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

    public function toggleActive(int $procedureId): void
    {
        $procedure = Procedure::findOrFail($procedureId);
        Gate::authorize('update', $procedure);
        $procedure->update(['is_active' => ! $procedure->is_active]);
        session()->flash('status', $procedure->is_active ? 'Procedimento reativado.' : 'Procedimento desativado. O histórico foi preservado.');
    }

    public function render()
    {
        Gate::authorize('viewAny', Procedure::class);
        $procedures = Procedure::query()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.mb_substr($this->search, 0, 120).'%'))
            ->when($this->status !== 'all', fn ($query) => $query->where('is_active', $this->status !== 'inactive'))
            ->orderBy('name')->orderBy('id')->paginate(15);

        return view('livewire.catalog.procedure-list', compact('procedures'));
    }
}
