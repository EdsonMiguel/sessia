<?php

namespace App\Livewire\Billing;

use App\Models\Payment;
use App\Models\Receivable;
use App\Modules\Billing\RecordPayment;
use App\Modules\Platform\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ReceivableDetail extends Component
{
    #[Locked]
    public int $receivableId;

    #[Locked]
    public string $paymentKey;

    public array $allocations = [];

    public string $method = 'pix';

    public string $paidAt = '';

    public string $reason = '';

    private function receivable(): Receivable
    {
        $r = Receivable::findOrFail($this->receivableId);
        Gate::authorize('view', $r);

        return $r;
    }

    public function mount(int $receivableId): void
    {
        $this->receivableId = $receivableId;
        $this->receivable();
        $this->paymentKey = (string) Str::uuid();
        $this->paidAt = now(app(CurrentTenant::class)->get()->timezone)->format('Y-m-d\TH:i');
    }

    public function receive(): void
    {
        $r = $this->receivable();
        $allocations = array_filter($this->allocations, fn ($value) => is_string($value) && trim($value) !== '');
        abort_unless($r->installments()->whereIn('id', array_keys($allocations))->count() === count($allocations), 404);
        app(RecordPayment::class)->handle($allocations, $this->method, $this->paidAt, $this->paymentKey);
        session()->flash('status', 'Recebimento registrado manualmente.');
        $this->redirectRoute('billing.show', $r->id);
    }

    public function reverse(int $paymentId): void
    {
        $r = $this->receivable();
        $payment = Payment::whereHas('allocations.installment', fn ($q) => $q->where('receivable_id', $r->id))->findOrFail($paymentId);
        app(RecordPayment::class)->reverse($payment->id, $this->reason);
        session()->flash('status', 'Recebimento estornado. O histórico e as parcelas foram preservados.');
    }

    public function render()
    {
        $receivable = $this->receivable()->load('customer', 'contract', 'installments.allocations.payment');
        $payments = Payment::with('allocations.installment')->whereHas('allocations.installment', fn ($q) => $q->where('receivable_id', $receivable->id))->orderByDesc('id')->get();
        $tenant = app(CurrentTenant::class)->get();

        return view('livewire.billing.receivable-detail', compact('receivable', 'payments', 'tenant'));
    }
}
