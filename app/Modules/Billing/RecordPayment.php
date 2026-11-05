<?php

namespace App\Modules\Billing;

use App\Models\Installment;
use App\Models\Payment;
use App\Modules\Catalog\DecimalAmount;
use App\Modules\Platform\TenantTransaction;
use App\Modules\Scheduling\LocalDateTime;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    public function handle(array $allocations, string $method, string $paidAt, string $key): Payment
    {
        Validator::make(compact('allocations', 'method', 'key'), [
            'allocations' => ['required', 'array', 'min:1', 'max:60'], 'allocations.*' => DecimalAmount::rules(),
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))], 'key' => ['required', 'uuid'],
        ])->validate();
        $paid = LocalDateTime::utc($paidAt, 'paidAt');
        if ($paid > now()) {
            throw ValidationException::withMessages(['paidAt' => 'A data de recebimento não pode estar no futuro.']);
        }

        return TenantTransaction::run(function () use ($allocations, $method, $paid, $key) {
            $normalized = [];
            foreach ($allocations as $id => $value) {
                if (! ctype_digit((string) $id) || (int) $id < 1 || DecimalAmount::cents($value) < 1) {
                    throw ValidationException::withMessages(['allocations' => 'Selecione parcelas válidas e valores maiores que zero.']);
                }
                $normalized[(int) $id] = DecimalAmount::normalize($value);
            }
            ksort($normalized);
            if ($existing = Payment::with('allocations')->where('idempotency_key', $key)->first()) {
                $previous = $existing->allocations->pluck('amount', 'installment_id')->all();
                ksort($previous);
                if ($previous !== $normalized || $existing->method !== $method || ! $existing->paid_at->equalTo($paid)) {
                    throw ValidationException::withMessages(['allocations' => 'Esta confirmação já foi usada com outros dados. Recarregue a página.']);
                }

                return $existing;
            }
            $customerId = null;
            $total = 0;
            foreach ($normalized as $id => $value) {
                $installment = Installment::with('receivable', 'allocations.payment')->findOrFail($id);
                $customerId ??= $installment->receivable->customer_id;
                if ($customerId !== $installment->receivable->customer_id || DecimalAmount::cents($value) > $installment->remainingCents()) {
                    throw ValidationException::withMessages(['allocations' => 'Os valores excedem o saldo ou as parcelas não pertencem à mesma cliente.']);
                }
                $total += DecimalAmount::cents($value);
            }
            if ($total > 999999999999) {
                throw ValidationException::withMessages(['allocations' => 'O recebimento ultrapassa o limite por lançamento. Divida-o em confirmações menores.']);
            }
            $payment = Payment::create(['customer_id' => $customerId, 'amount' => DecimalAmount::fromCents($total), 'paid_at' => $paid, 'method' => $method, 'idempotency_key' => $key, 'recorded_by' => auth()->id()]);
            foreach ($normalized as $id => $amount) {
                $payment->allocations()->create(['installment_id' => $id, 'amount' => $amount]);
            }

            return $payment;
        });
    }

    public function reverse(int $id, string $reason): void
    {
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:1000']])->validate();
        TenantTransaction::run(function () use ($id, $reason) {
            $payment = Payment::findOrFail($id);
            if ($payment->reversed_at !== null) {
                return;
            }
            $payment->update(['reversed_at' => now(), 'reversed_by' => auth()->id(), 'reversal_reason' => $reason]);
        });
    }
}
