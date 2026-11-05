<?php

namespace App\Livewire\Billing;

use App\Models\Payment;
use App\Models\Receivable;
use App\Modules\Billing\RecordPayment;
use App\Modules\Catalog\DecimalAmount;
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
        $receivable = $this->receivable()->load('customer', 'contract.items', 'installments.allocations.payment');
        $payments = Payment::with('allocations.installment')->whereHas('allocations.installment', fn ($q) => $q->where('receivable_id', $receivable->id))->orderByDesc('id')->get();
        $tenant = app(CurrentTenant::class)->get();
        $pixData = [
            'key' => $tenant->pix_key,
            'receiver' => $tenant->pix_receiver,
            'city' => $tenant->pix_city,
            'phone' => $receivable->customer->phone,
            'description' => $receivable->description.' · '.$receivable->contract->items->pluck('procedure_name')->implode(', '),
            'installments' => $receivable->installments->filter(fn ($i) => $i->remainingCents() > 0)->map(fn ($i) => [
                'id' => (string) $i->id,
                'number' => $i->number,
                'due' => $i->due_on->format('d/m/Y'),
                'amount' => DecimalAmount::fromCents($i->remainingCents()),
                'formattedAmount' => DecimalAmount::format(DecimalAmount::fromCents($i->remainingCents())),
                'txid' => 'S'.$receivable->id.'P'.$i->id,
                'reference' => 'Cobranca '.$receivable->id.' Parcela '.$i->number,
            ])->values()->all(),
        ];

        return view('livewire.billing.receivable-detail', compact('receivable', 'payments', 'tenant', 'pixData'));
    }
}
