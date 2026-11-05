<?php

namespace App\Livewire\Contract;

use App\Models\ServiceContract;
use App\Modules\Billing\IssueReceivable;
use App\Modules\Contract\CreateContract;
use App\Modules\Contract\GeneratePlanHorizon;
use App\Modules\Contract\GeneratePlanPeriod;
use App\Modules\Contract\GenerateRecurringAppointments;
use App\Modules\Platform\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ContractDetail extends Component
{
    #[Locked]
    public int $contractId;

    public array $dueDates = [];

    public string $month = '';

    public bool $confirmPartial = false;

    public string $partialAmount = '';

    public array $partialQuantities = [];

    public string $status = 'paused';

    public string $reason = '';

    public array $generationMessages = [];

    private function contract(): ServiceContract
    {
        $contract = ServiceContract::findOrFail($this->contractId);
        Gate::authorize('view', $contract);

        return $contract;
    }

    public function mount(int $contractId): void
    {
        $this->contractId = $contractId;
        $contract = $this->contract();
        $now = now(app(CurrentTenant::class)->get()->timezone);
        $this->dueDates = [$now->toDateString()];
        $this->month = $now->format('Y-m');
        $this->partialAmount = $contract->total;
        $this->partialQuantities = $contract->items->pluck('quantity', 'id')->all();
    }

    public function addDueDate(): void
    {
        if (count($this->dueDates) >= 60) {
            return;
        }
        try {
            $date = CarbonImmutable::parse(end($this->dueDates))->addMonthNoOverflow()->toDateString();
        } catch (\Throwable) {
            $date = now()->toDateString();
        }
        $this->dueDates[] = $date;
    }

    public function removeDueDate(int $index): void
    {
        unset($this->dueDates[$index]);
        $this->dueDates = array_values($this->dueDates);
    }

    public function issueCharge(): void
    {
        $contract = $this->contract();
        $receivable = app(IssueReceivable::class)->handle($contract->id, $this->dueDates);
        $this->redirectRoute('billing.show', $receivable->id);
    }

    public function createPeriod(): void
    {
        $contract = $this->contract();
        app(GeneratePlanPeriod::class)->handle($contract->id, $this->month, $this->confirmPartial ? ['confirmed' => true, 'amount' => $this->partialAmount, 'quantities' => $this->partialQuantities] : null);
        session()->flash('status', 'Competência criada. Agenda e mensalidade podem ser geradas separadamente.');
    }

    public function billPeriod(int $id): void
    {
        $contract = $this->contract();
        $period = $contract->periods()->findOrFail($id);
        $receivable = app(IssueReceivable::class)->handle($contract->id, [$period->due_on->toDateString()], $period->id);
        $this->redirectRoute('billing.show', $receivable->id);
    }

    public function schedulePeriod(int $id): void
    {
        $period = $this->contract()->periods()->findOrFail($id);
        $this->generationMessages = app(GenerateRecurringAppointments::class)->handle($period->id);
        session()->flash('status', 'Geração da agenda concluída. Confira os avisos e os horários.');
    }

    public function generateHorizon(): void
    {
        $this->generationMessages = app(GeneratePlanHorizon::class)->handle($this->contract()->id, 2);
        session()->flash('status', 'Competências, mensalidades e agenda verificadas para o mês atual e o próximo.');
    }

    public function changeStatus(): void
    {
        app(CreateContract::class)->changeStatus($this->contract()->id, $this->status, $this->reason);
        $this->reason = '';
        session()->flash('status', 'Situação do plano atualizada. Compromissos e cobranças existentes foram preservados para revisão.');
    }

    public function render()
    {
        $contract = $this->contract()->load(['customer', 'items' => fn ($q) => $q->withCount(['activeExecutions', 'reservations']), 'periods' => fn ($q) => $q->latest('month'), 'periods.items' => fn ($q) => $q->withCount(['activeExecutions', 'reservations']), 'periods.items.contractItem', 'periods.receivable', 'receivables', 'events.user']);

        return view('livewire.contract.contract-detail', compact('contract'));
    }
}
