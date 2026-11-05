<?php

namespace App\Models;

class AppointmentItem extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function execution()
    {
        return $this->hasOne(ServiceExecution::class);
    }

    public function contractItem()
    {
        return $this->belongsTo(ContractItem::class);
    }

    public function periodItem()
    {
        return $this->belongsTo(PlanPeriodItem::class, 'plan_period_item_id');
    }
}
