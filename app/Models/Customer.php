<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'email', 'notes', 'is_active'])]
class Customer extends TenantRecord
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function procedurePrices(): HasMany
    {
        return $this->hasMany(CustomerProcedurePrice::class);
    }
}
