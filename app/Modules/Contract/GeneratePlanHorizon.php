<?php

namespace App\Modules\Contract;

use App\Models\ServiceContract;
use App\Modules\Billing\IssueReceivable;
use App\Modules\Catalog\DecimalAmount;
use App\Modules\Platform\CurrentTenant;
use App\Modules\Platform\TenantTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GeneratePlanHorizon
{
    public function handle(int $contractId, int $months = 2): array
    {
        Validator::make(compact('months'), ['months' => ['integer', 'between:1,3']])->validate();
        $contract = ServiceContract::where('type', 'plan')->findOrFail($contractId);
        $messages = [];
        $month = now(app(CurrentTenant::class)->get()->timezone)->startOfMonth()->toImmutable();
        for ($i = 0; $i < $months; $i++) {
            $target = $month->addMonths($i);
            try {
                $period = app(GeneratePlanPeriod::class)->handle($contract->id, $target->format('Y-m'));
                if ($contract->status !== 'active') {
                    continue;
                }
                // Billing does not depend on finding free time in the calendar.
                TenantTransaction::run(function () use ($contract, $period) {
                    if (ServiceContract::findOrFail($contract->id)->status === 'active' && DecimalAmount::cents($period->amount) > 0) {
                        app(IssueReceivable::class)->handle($contract->id, [$period->due_on->toDateString()], $period->id);
                    }
                });
                $messages = array_merge($messages, app(GenerateRecurringAppointments::class)->handle($period->id));
            } catch (ValidationException $e) {
                $messages[] = $target->format('m/Y').': '.implode(' ', array_merge(...array_values($e->errors())));
            }
        }

        return $messages;
    }
}
