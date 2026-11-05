<?php

namespace App\Modules\Scheduling;

use App\Models\Appointment;
use App\Models\ContractItem;
use App\Models\Customer;
use App\Models\PlanPeriodItem;
use App\Models\Procedure;
use App\Models\ScheduleBlock;
use App\Modules\Contract\SessionBalance;
use App\Modules\Platform\CurrentTenant;
use App\Modules\Platform\TenantTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScheduleAppointment
{
    public function handle(array $input, ?int $appointmentId = null, ?string $recurrenceKey = null): Appointment
    {
        $data = Validator::make($input, [
            'customer_id' => ['required', 'integer'], 'address_id' => ['required', 'integer'],
            'starts_at' => ['required', 'string'], 'preparation_minutes' => ['required', 'integer', 'between:0,1440'],
            'travel_minutes' => ['required', 'integer', 'between:0,1440'], 'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:30'], 'items.*.procedure_id' => ['required', 'integer'],
            'items.*.contract_item_id' => ['nullable', 'integer'], 'items.*.plan_period_item_id' => ['nullable', 'integer'],
        ])->validate();

        return TenantTransaction::run(function () use ($data, $appointmentId, $recurrenceKey) {
            if ($recurrenceKey !== null && ($existing = Appointment::where('recurrence_key', $recurrenceKey)->first())) {
                return $existing;
            }
            $customer = Customer::findOrFail($data['customer_id']);
            $address = $customer->addresses()->findOrFail($data['address_id']);
            $appointment = $appointmentId ? Appointment::findOrFail($appointmentId) : new Appointment;
            if ($appointment->exists && ! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
                throw ValidationException::withMessages(['appointment' => 'Somente agendamentos em aberto podem ser editados.']);
            }
            if ($appointment->exists && $appointment->recurrence_key && $appointment->customer_id !== $customer->id) {
                throw ValidationException::withMessages(['customer_id' => 'Uma ocorrência do plano deve manter a cliente contratada.']);
            }
            $start = LocalDateTime::utc($data['starts_at']);
            $localDate = $start->timezone(app(CurrentTenant::class)->get()->timezone)->toDateString();
            $rows = [];
            $requested = [];
            foreach ($data['items'] as $index => $row) {
                $procedure = Procedure::findOrFail($row['procedure_id']);
                $contractItem = ! empty($row['contract_item_id']) ? ContractItem::with('contract')->findOrFail($row['contract_item_id']) : null;
                $periodItem = ! empty($row['plan_period_item_id']) ? PlanPeriodItem::with('period', 'contractItem.contract')->findOrFail($row['plan_period_item_id']) : null;
                if ($periodItem && (! $contractItem || $periodItem->contract_item_id !== $contractItem->id)) {
                    throw ValidationException::withMessages(["items.$index" => 'A sessão não corresponde ao item contratado.']);
                }
                if ($contractItem) {
                    $contract = $contractItem->contract;
                    if ($contract->customer_id !== $customer->id || $contractItem->procedure_id !== $procedure->id || $contract->status !== 'active'
                        || ($contract->type === 'plan') !== ($periodItem !== null)
                        || $localDate < $contract->starts_on->toDateString() || ($contract->ends_on && $localDate > $contract->ends_on->toDateString())
                        || ($periodItem && ($periodItem->period->service_contract_id !== $contract->id || $periodItem->period->month->format('Y-m') !== substr($localDate, 0, 7)))) {
                        throw ValidationException::withMessages(["items.$index" => 'Cliente, procedimento, competência ou vigência não correspondem ao contrato ativo.']);
                    }
                    $balanceItem = $periodItem ?? $contractItem;
                    $key = ($periodItem ? 'period:' : 'contract:').$balanceItem->id;
                    $requested[$key] = ($requested[$key] ?? 0) + 1;
                    if (SessionBalance::for($balanceItem, $appointmentId)['available'] < $requested[$key]) {
                        throw ValidationException::withMessages(["items.$index" => 'Não há saldo disponível para reservar esta sessão.']);
                    }
                }
                $rows[] = [
                    'procedure_id' => $procedure->id, 'contract_item_id' => $contractItem?->id,
                    'plan_period_item_id' => $periodItem?->id, 'procedure_name' => $contractItem?->procedure_name ?? $procedure->name,
                    'duration_minutes' => $contractItem?->duration_minutes ?? $procedure->duration_minutes,
                ];
            }
            $end = $start->addMinutes(array_sum(array_column($rows, 'duration_minutes')));
            $from = $start->subMinutes($data['preparation_minutes']);
            $until = $end->addMinutes($data['travel_minutes']);
            $this->assertFree($from, $until, $appointmentId);
            $before = $appointment->exists ? $appointment->load('items')->toArray() : null;
            $appointment->fill([
                'customer_id' => $customer->id, 'address_snapshot' => $address->only(['postal_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'reference']) + ['formatted' => $address->formatted, 'address_id' => $address->id],
                'starts_at' => $start, 'ends_at' => $end, 'occupied_from' => $from, 'occupied_until' => $until,
                'preparation_minutes' => $data['preparation_minutes'], 'travel_minutes' => $data['travel_minutes'],
                'notes' => $data['notes'] ?? null,
            ]);
            if (! $appointment->exists) {
                $appointment->status = 'scheduled';
                $appointment->recurrence_key = $recurrenceKey;
            }
            $appointment->save();
            $appointment->items()->delete();
            foreach ($rows as $row) {
                $appointment->items()->create($row);
            }
            $appointment->events()->create([
                'event' => $before ? 'rescheduled' : 'created', 'user_id' => auth()->id(),
                'before' => $before, 'after' => $appointment->fresh()->load('items')->toArray(),
            ]);

            return $appointment->fresh();
        });
    }

    public function assertFree(CarbonImmutable $from, CarbonImmutable $until, ?int $except = null): void
    {
        $conflict = Appointment::whereIn('status', ['scheduled', 'confirmed', 'completed'])
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->where('occupied_from', '<', $until)->where('occupied_until', '>', $from)->exists();
        $blocked = ScheduleBlock::whereNull('cancelled_at')->where('starts_at', '<', $until)->where('ends_at', '>', $from)->exists();
        if ($conflict || $blocked) {
            throw ValidationException::withMessages(['starts_at' => 'Este período conflita com um atendimento ou bloqueio, incluindo preparação e deslocamento.']);
        }
    }

    public function changeStatus(int $id, string $status, ?string $reason): void
    {
        Validator::make(compact('status', 'reason'), ['status' => ['required', 'in:confirmed,cancelled,no_show'], 'reason' => ['nullable', 'string', 'max:1000']])->validate();
        TenantTransaction::run(function () use ($id, $status, $reason) {
            $appointment = Appointment::findOrFail($id);
            if ($appointment->status === $status) {
                return;
            }
            if (! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
                throw ValidationException::withMessages(['appointment' => 'Este atendimento já foi encerrado.']);
            }
            $before = $appointment->toArray();
            $appointment->update(['status' => $status]);
            $appointment->events()->create(['event' => $status, 'reason' => $reason, 'user_id' => auth()->id(), 'before' => $before, 'after' => $appointment->toArray()]);
        });
    }

    public function block(string $startsAt, string $endsAt, string $reason): ScheduleBlock
    {
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:255']])->validate();

        return TenantTransaction::run(function () use ($startsAt, $endsAt, $reason) {
            $from = LocalDateTime::utc($startsAt, 'blockStart');
            $until = LocalDateTime::utc($endsAt, 'blockEnd');
            if ($from >= $until) {
                throw ValidationException::withMessages(['blockEnd' => 'O fim precisa ser posterior ao início.']);
            }
            $this->assertFree($from, $until);

            return ScheduleBlock::create(['starts_at' => $from, 'ends_at' => $until, 'reason' => $reason, 'created_by' => auth()->id()]);
        });
    }

    public function unblock(int $id): void
    {
        TenantTransaction::run(fn () => ScheduleBlock::findOrFail($id)->update(['cancelled_at' => now(), 'cancelled_by' => auth()->id()]));
    }
}
