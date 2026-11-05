<?php

namespace App\Models;

class ServiceExecution extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['performed_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime'];
    }
}
