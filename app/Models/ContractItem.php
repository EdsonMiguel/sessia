<?php

namespace App\Models;

class ContractItem extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }

    public function contract()
    {
        return $this->belongsTo(ServiceContract::class, 'service_contract_id');
    }

    public function activeExecutions()
    {
        return $this->hasMany(ServiceExecution::class)->whereNull('reversed_at');
    }

    public function reservations()
    {
        return $this->hasMany(AppointmentItem::class)->whereHas('appointment', fn ($q) => $q->whereIn('status', ['scheduled', 'confirmed']));
    }
}
