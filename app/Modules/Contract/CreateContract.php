<?php

namespace App\Modules\Contract;

use App\Models\Customer;
use App\Models\Procedure;
use App\Models\ServiceContract;
use App\Modules\Catalog\DecimalAmount;
use App\Modules\Platform\TenantTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateContract
{
    public function handle(array $input): ServiceContract
    {
        $data = Validator::make($input, [
            'customer_id' => ['required', 'integer'], 'type' => ['required', Rule::in(array_keys(ServiceContract::TYPES))],
            'total' => DecimalAmount::rules(), 'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'weekday' => ['nullable', 'required_if:type,plan', 'integer', 'between:0,6'],
            'preferred_time' => ['nullable', 'required_if:type,plan', 'date_format:H:i'],
            'due_day' => ['nullable', 'required_if:type,plan', 'integer', 'between:1,31'],
            'address_id' => ['nullable', 'required_if:type,plan', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.procedure_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'between:1,65535'],
            'items.*.unit_price' => ['nullable', ...array_slice(DecimalAmount::rules(), 1)],
        ])->validate();

        return TenantTransaction::run(function () use ($data) {
            $customer = Customer::where('is_active', true)->findOrFail($data['customer_id']);
            $address = $data['type'] === 'plan' && ! empty($data['address_id']) ? $customer->addresses()->findOrFail($data['address_id']) : null;
            $plan = $data['type'] === 'plan';
            $contract = ServiceContract::create([
                'customer_id' => $customer->id, 'type' => $data['type'], 'total' => DecimalAmount::normalize($data['total']),
                'starts_on' => $data['starts_on'], 'ends_on' => $plan ? ($data['ends_on'] ?? null) : null, 'status' => 'active',
                'address_id' => $plan ? $address?->id : null,
                'weekday' => $plan ? $data['weekday'] : null, 'preferred_time' => $plan ? $data['preferred_time'] : null,
                'due_day' => $plan ? $data['due_day'] : null, 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($data['items'] as $item) {
                $procedure = Procedure::where('is_active', true)->findOrFail($item['procedure_id']);
                $price = $item['unit_price'] ?? null;
                if ($price === null || $price === '') {
                    $price = $customer->procedurePrices()->where('procedure_id', $procedure->id)->first()?->price ?? $procedure->default_price;
                }
                $contract->items()->create([
                    'procedure_id' => $procedure->id, 'procedure_name' => $procedure->name,
                    'duration_minutes' => $procedure->duration_minutes, 'quantity' => $item['quantity'],
                    'unit_price' => DecimalAmount::normalize($price),
                ]);
            }
            $contract->events()->create(['event' => 'created', 'user_id' => auth()->id()]);

            return $contract;
        });
    }

    public function changeStatus(int $id, string $status, string $reason): void
    {
        Validator::make(compact('status', 'reason'), ['status' => ['required', Rule::in(['active', 'paused', 'ended'])], 'reason' => ['required', 'string', 'max:1000']])->validate();
        TenantTransaction::run(function () use ($id, $status, $reason) {
            $contract = ServiceContract::where('type', 'plan')->findOrFail($id);
            abort_if($contract->status === 'ended' && $status !== 'ended', 422, 'Um plano encerrado não pode ser reativado.');
            if ($contract->status === $status) {
                return;
            }
            $contract->update(['status' => $status]);
            $contract->events()->create(['event' => $status, 'reason' => $reason, 'user_id' => auth()->id()]);
        });
    }
}
