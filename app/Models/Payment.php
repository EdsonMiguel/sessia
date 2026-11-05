<?php

namespace App\Models;

class Payment extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    public const METHODS = ['pix' => 'Pix', 'cash' => 'Dinheiro', 'transfer' => 'Transferência', 'card' => 'Cartão', 'other' => 'Outro'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime'];
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
