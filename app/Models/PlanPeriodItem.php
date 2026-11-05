<?php

namespace App\Models;

class PlanPeriodItem extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    public function period()
    {
        return $this->belongsTo(PlanPeriod::class, 'plan_period_id');
    }

    public function contractItem()
    {
        return $this->belongsTo(ContractItem::class);
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
