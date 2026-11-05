<?php

namespace App\Livewire\Billing;

use App\Models\Installment;
use App\Modules\Catalog\DecimalAmount;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class FinanceList extends Component
{
    use WithPagination;

    public string $status = 'open';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('viewAny', Installment::class);
        $all = Installment::with('receivable.customer', 'allocations.payment')->orderBy('due_on')->orderBy('id')->get();
        $pending = $all->sum(fn ($i) => $i->remainingCents());
        $overdue = $all->filter(fn ($i) => $i->overdue())->sum(fn ($i) => $i->remainingCents());
        $filtered = $all->filter(fn ($i) => match ($this->status) {
            'paid' => $i->remainingCents() === 0, 'overdue' => $i->overdue(), 'partial' => $i->status() === 'partial', 'all' => true, default => $i->remainingCents() > 0
        });
        $page = $this->getPage();
        $installments = new LengthAwarePaginator($filtered->forPage($page, 20)->values(), $filtered->count(), 20, $page, ['path' => route('billing.index')]);

        return view('livewire.billing.finance-list', ['installments' => $installments, 'pending' => DecimalAmount::fromCents($pending), 'overdue' => DecimalAmount::fromCents($overdue)]);
    }
}
