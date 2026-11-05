<?php

namespace App\Models;

class Receivable extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract()
    {
        return $this->belongsTo(ServiceContract::class, 'service_contract_id');
    }

    public function period()
    {
        return $this->belongsTo(PlanPeriod::class, 'plan_period_id');
    }

    public function installments()
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }
}
