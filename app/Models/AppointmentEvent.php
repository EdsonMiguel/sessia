<?php

namespace App\Models;

class AppointmentEvent extends TenantRecord
{
    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
