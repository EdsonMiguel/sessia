<?php

namespace App\Modules\Billing;

use App\Models\PlanPeriod;
use App\Models\Receivable;
use App\Models\ServiceContract;
use App\Modules\Catalog\DecimalAmount;
use App\Modules\Platform\TenantTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class IssueReceivable
{
    public function handle(int $contractId, array $dueDates, ?int $periodId = null): Receivable
    {
        Validator::make(['dueDates' => $dueDates], ['dueDates' => ['required', 'array', 'min:1', 'max:60'], 'dueDates.*' => ['required', 'date_format:Y-m-d']])->validate();
        $dueDates = array_values($dueDates);

        return TenantTransaction::run(function () use ($contractId, $dueDates, $periodId) {
            $contract = ServiceContract::findOrFail($contractId);
            $period = $periodId ? PlanPeriod::where('service_contract_id', $contract->id)->findOrFail($periodId) : null;
            if (($contract->type === 'plan') !== ($period !== null)) {
                throw ValidationException::withMessages(['dueDates' => 'A mensalidade precisa estar vinculada à competência do plano.']);
            }
            if ($contract->type === 'package' && $contract->status !== 'completed') {
                throw ValidationException::withMessages(['dueDates' => 'A cobrança final do pacote só pode ser liberada após todas as sessões.']);
            }
            if ($period && ($dueDates !== [$period->due_on->toDateString()])) {
                throw ValidationException::withMessages(['dueDates' => 'Use o vencimento acordado da competência mensal.']);
            }
            $source = $period ? 'period:'.$period->id : 'contract:'.$contract->id;
            $amount = $period?->amount ?? $contract->total;
            if ($existing = Receivable::with('installments')->where('source_key', $source)->first()) {
                if ($existing->installments->pluck('due_on')->map(fn ($date) => $date->toDateString())->all() !== $dueDates) {
                    throw ValidationException::withMessages(['dueDates' => 'A cobrança já existe com outros vencimentos. Consulte o financeiro.']);
                }

                return $existing;
            }
            $cents = DecimalAmount::cents($amount);
            if ($cents < count($dueDates)) {
                throw ValidationException::withMessages(['dueDates' => 'O valor deve permitir pelo menos um centavo por parcela.']);
            }
            $receivable = Receivable::create([
                'customer_id' => $contract->customer_id, 'service_contract_id' => $contract->id, 'plan_period_id' => $period?->id,
                'source_key' => $source, 'description' => ServiceContract::TYPES[$contract->type].' #'.$contract->id.($period ? ' · '.$period->month->format('m/Y') : ''),
                'amount' => $amount,
            ]);
            $base = intdiv($cents, count($dueDates));
            $remainder = $cents % count($dueDates);
            foreach ($dueDates as $index => $date) {
                $receivable->installments()->create(['number' => $index + 1, 'due_on' => $date, 'amount' => DecimalAmount::fromCents($base + ($index < $remainder ? 1 : 0))]);
            }

            return $receivable;
        });
    }
}
