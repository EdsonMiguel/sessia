<?php

namespace App\Modules\Contract;

use App\Models\PlanPeriod;
use App\Modules\Platform\CurrentTenant;
use App\Modules\Scheduling\ScheduleAppointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class GenerateRecurringAppointments
{
    public function handle(int $periodId): array
    {
        $period = PlanPeriod::with('contract', 'items.contractItem')->findOrFail($periodId);
        $contract = $period->contract;
        if ($contract->status !== 'active') {
            return ['Plano pausado ou encerrado: nenhum novo agendamento gerado.'];
        }
        $tz = app(CurrentTenant::class)->get()->timezone;
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $period->month->toDateString(), $tz);
        $ordinal = 0;
        $messages = [];
        while ($day->format('Y-m') === $period->month->format('Y-m')) {
            if ($day->dayOfWeek === (int) $contract->weekday && $day->toDateString() >= $contract->starts_on->toDateString()
                && (! $contract->ends_on || $day->toDateString() <= $contract->ends_on->toDateString())) {
                $rows = [];
                foreach ($period->items as $item) {
                    if ($ordinal < $item->quantity) {
                        $rows[] = ['procedure_id' => $item->contractItem->procedure_id, 'contract_item_id' => $item->contract_item_id, 'plan_period_item_id' => $item->id];
                    }
                }
                $ordinal++;
                $at = $day->setTimeFromTimeString($contract->preferred_time);
                if ($rows && $at >= now()) {
                    try {
                        app(ScheduleAppointment::class)->handle([
                            'customer_id' => $contract->customer_id, 'address_id' => $contract->address_id,
                            'starts_at' => $at->format('Y-m-d\TH:i'), 'preparation_minutes' => 0, 'travel_minutes' => 0,
                            'items' => $rows, 'notes' => 'Ocorrência do plano #'.$contract->id,
                        ], recurrenceKey: 'period:'.$period->id.':'.$day->toDateString());
                    } catch (ValidationException|ModelNotFoundException $e) {
                        $messages[] = $day->format('d/m/Y').': '.($e instanceof ValidationException ? implode(' ', array_merge(...array_values($e->errors()))) : 'Revise o endereço e os itens do plano.');
                    }
                }
            }
            $day = $day->addDay();
        }

        return $messages;
    }
}
