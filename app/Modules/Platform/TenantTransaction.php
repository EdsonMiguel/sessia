<?php

namespace App\Modules\Platform;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TenantTransaction
{
    public static function run(Closure $callback): mixed
    {
        $context = app(CurrentTenant::class);
        $tenant = $context->get();
        if (! $context->isJob()) {
            Gate::authorize('update', $tenant);
        }

        return DB::transaction(function () use ($tenant, $callback) {
            // One professional per business: a common first lock serializes
            // scheduling, reservations and financial writes, including empty ranges.
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            return $callback($tenant);
        }, 3);
    }
}
