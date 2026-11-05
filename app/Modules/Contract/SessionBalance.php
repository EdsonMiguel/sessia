<?php

namespace App\Modules\Contract;

use App\Models\ContractItem;
use App\Models\PlanPeriodItem;

class SessionBalance
{
    public static function for(ContractItem|PlanPeriodItem $item, ?int $exceptAppointment = null): array
    {
        $performed = $exceptAppointment === null && isset($item->active_executions_count) ? $item->active_executions_count : $item->activeExecutions()->count();
        $reserved = $exceptAppointment === null && isset($item->reservations_count) ? $item->reservations_count
            : $item->reservations()->when($exceptAppointment, fn ($q) => $q->where('appointment_id', '!=', $exceptAppointment))->count();

        return ['contracted' => $item->quantity, 'performed' => $performed, 'reserved' => $reserved, 'available' => $item->quantity - $performed - $reserved];
    }
}
