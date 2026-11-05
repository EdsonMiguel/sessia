<?php

namespace App\Models;

class ContractEvent extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
