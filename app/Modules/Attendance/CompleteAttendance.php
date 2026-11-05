<?php

namespace App\Modules\Attendance;

use App\Models\Appointment;
use App\Models\ContractItem;
use App\Models\PlanPeriodItem;
use App\Models\ServiceContract;
use App\Models\ServiceExecution;
use App\Modules\Platform\TenantTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompleteAttendance
{
    public function handle(int $id): void
    {
        TenantTransaction::run(function () use ($id) {
            $appointment = Appointment::with('items')->findOrFail($id);
            if ($appointment->status === 'completed') {
                return;
            }
            if (! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
                throw ValidationException::withMessages(['appointment' => 'Este atendimento não pode ser concluído.']);
            }
            $contracts = [];
            foreach ($appointment->items as $row) {
                $item = $row->plan_period_item_id ? PlanPeriodItem::findOrFail($row->plan_period_item_id)
                    : ($row->contract_item_id ? ContractItem::findOrFail($row->contract_item_id) : null);
                if ($item && $item->activeExecutions()->count() >= $item->quantity) {
                    throw ValidationException::withMessages(['appointment' => 'Saldo de sessões indisponível. Revise as reservas e execuções.']);
                }
                ServiceExecution::create([
                    'appointment_item_id' => $row->id, 'contract_item_id' => $row->contract_item_id,
                    'plan_period_item_id' => $row->plan_period_item_id, 'performed_at' => now(), 'performed_by' => auth()->id(),
                ]);
                if ($row->contract_item_id) {
                    $contracts[] = ContractItem::findOrFail($row->contract_item_id)->service_contract_id;
                }
            }
            $appointment->update(['status' => 'completed']);
            $appointment->events()->create(['event' => 'completed', 'user_id' => auth()->id()]);
            foreach (array_unique($contracts) as $contractId) {
                $contract = ServiceContract::with(['items' => fn ($q) => $q->withCount('activeExecutions')])->findOrFail($contractId);
                if ($contract->type !== 'plan' && $contract->items->every(fn ($item) => $item->active_executions_count === $item->quantity)) {
                    $contract->update(['status' => 'completed']);
                    $contract->events()->create(['event' => 'completed', 'user_id' => auth()->id()]);
                }
            }
        });
    }

    public function reverse(int $id, string $reason): void
    {
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:1000']])->validate();
        TenantTransaction::run(function () use ($id, $reason) {
            $appointment = Appointment::with('items')->findOrFail($id);
            if ($appointment->status === 'cancelled' && $appointment->events()->where('event', 'execution_reversed')->exists()) {
                return;
            }
            if ($appointment->status !== 'completed') {
                throw ValidationException::withMessages(['reason' => 'Somente uma execução concluída pode ser estornada.']);
            }
            foreach ($appointment->items as $row) {
                $execution = ServiceExecution::where('appointment_item_id', $row->id)->whereNull('reversed_at')->firstOrFail();
                $execution->update(['reversed_at' => now(), 'reversed_by' => auth()->id(), 'reversal_reason' => $reason]);
                if ($row->contract_item_id) {
                    $contract = ContractItem::findOrFail($row->contract_item_id)->contract;
                    if ($contract->type !== 'plan' && $contract->status === 'completed') {
                        $contract->update(['status' => 'active']);
                        $contract->events()->create(['event' => 'execution_reversed', 'reason' => $reason, 'user_id' => auth()->id()]);
                    }
                }
            }
            $appointment->update(['status' => 'cancelled']);
            $appointment->events()->create(['event' => 'execution_reversed', 'reason' => $reason, 'user_id' => auth()->id()]);
        });
    }
}
