<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'description', 'duration_minutes', 'default_price', 'is_active'])]
class Procedure extends TenantRecord
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'duration_minutes' => 'integer', 'default_price' => 'decimal:2'];
    }
}
