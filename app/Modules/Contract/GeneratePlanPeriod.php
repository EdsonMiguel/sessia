<?php

namespace App\Modules\Contract;

use App\Models\PlanPeriod;
use App\Models\ServiceContract;
use App\Modules\Catalog\DecimalAmount;
use App\Modules\Platform\CurrentTenant;
use App\Modules\Platform\TenantTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GeneratePlanPeriod
{
    public function handle(int $contractId, string $month, ?array $partialTerms = null): PlanPeriod
    {
        Validator::make(compact('month'), ['month' => ['required', 'date_format:Y-m']])->validate();

        return TenantTransaction::run(function () use ($contractId, $month, $partialTerms) {
            $contract = ServiceContract::with('items')->where('type', 'plan')->findOrFail($contractId);
            $first = CarbonImmutable::createFromFormat('!Y-m', $month, app(CurrentTenant::class)->get()->timezone);
            $last = $first->endOfMonth();
            if ($existing = $contract->periods()->whereDate('month', $first->toDateString())->first()) {
                return $existing;
            }
            if ($contract->status !== 'active' || $last->toDateString() < $contract->starts_on->toDateString()
                || ($contract->ends_on && $first->toDateString() > $contract->ends_on->toDateString())) {
                throw ValidationException::withMessages(['generation' => 'O plano não está ativo nesta competência.']);
            }
            $partial = $first->toDateString() < $contract->starts_on->toDateString()
                || ($contract->ends_on && $last->toDateString() > $contract->ends_on->toDateString());
            $amount = $contract->total;
            $quantities = $contract->items->pluck('quantity', 'id')->all();
            if ($partial) {
                if (! $partialTerms) {
                    throw ValidationException::withMessages(['generation' => 'Competência parcial: confirme o valor e as quantidades acordados antes de gerar.']);
                }
                Validator::make($partialTerms, [
                    'confirmed' => ['accepted'], 'amount' => DecimalAmount::rules(),
                    'quantities' => ['required', 'array'], 'quantities.*' => ['required', 'integer', 'between:1,65535'],
                ])->validate();
                if (array_diff(array_keys($quantities), array_keys($partialTerms['quantities'])) || array_diff(array_keys($partialTerms['quantities']), array_keys($quantities))) {
                    throw ValidationException::withMessages(['generation' => 'Informe as quantidades de todos os itens do plano.']);
                }
                $amount = DecimalAmount::normalize($partialTerms['amount']);
                $quantities = $partialTerms['quantities'];
            }
            $due = $first->day(min((int) $contract->due_day, $first->daysInMonth));
            $period = $contract->periods()->create(['month' => $first->toDateString(), 'due_on' => $due->toDateString(), 'amount' => $amount]);
            foreach ($contract->items as $item) {
                $period->items()->create(['contract_item_id' => $item->id, 'quantity' => $quantities[$item->id]]);
            }
            $terms = $contract->items->map(fn ($item) => $item->procedure_name.': '.$quantities[$item->id].' sessões')->implode('; ');
            $contract->events()->create(['event' => 'period_created', 'user_id' => auth()->id(), 'reason' => $month.($partial ? ' · condições parciais confirmadas: '.DecimalAmount::format($amount).' · '.$terms : '')]);

            return $period;
        });
    }
}
