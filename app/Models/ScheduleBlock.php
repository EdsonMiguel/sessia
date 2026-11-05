<?php

namespace App\Models;

class ScheduleBlock extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}
