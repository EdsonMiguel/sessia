<?php

namespace App\Models;

use App\Modules\Platform\CurrentTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class TenantRecord extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $query->where($query->getModel()->qualifyColumn('tenant_id'), app(CurrentTenant::class)->get()->id);
        });

        static::saving(function (TenantRecord $record) {
            $tenantId = app(CurrentTenant::class)->get()->id;
            if (($record->exists && (int) $record->getRawOriginal('tenant_id') !== $tenantId)
                || ($record->tenant_id !== null && (int) $record->tenant_id !== $tenantId)) {
                throw new AuthorizationException('Este registro pertence a outro negócio.');
            }
            $record->tenant_id = $tenantId;
        });

        static::deleting(function (TenantRecord $record) {
            if ((int) $record->tenant_id !== app(CurrentTenant::class)->get()->id) {
                throw new AuthorizationException('Este registro pertence a outro negócio.');
            }
        });
    }
}
