<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'total', 'is_active', 'show_in_catalog'])]
class ProcedurePackage extends TenantRecord
{
    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'is_active' => 'boolean', 'show_in_catalog' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcedurePackageItem::class)->orderBy('id');
    }
}
