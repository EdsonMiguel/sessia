<?php

namespace App\Models;

class PlanPeriod extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['month' => 'immutable_date', 'due_on' => 'immutable_date', 'amount' => 'decimal:2'];
    }

    public function contract()
    {
        return $this->belongsTo(ServiceContract::class, 'service_contract_id');
    }

    public function items()
    {
        return $this->hasMany(PlanPeriodItem::class);
    }

    public function receivable()
    {
        return $this->hasOne(Receivable::class);
    }
}
