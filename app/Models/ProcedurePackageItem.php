<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['procedure_package_id', 'procedure_id', 'quantity'])]
class ProcedurePackageItem extends TenantRecord
{
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }
}
