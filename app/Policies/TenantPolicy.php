<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\CurrentTenant;

class TenantPolicy
{
    public function update(User $user, Tenant $tenant): bool
    {
        return app(CurrentTenant::class)->get()->is($tenant)
            && $user->tenants()->where('tenants.id', $tenant->id)
                ->wherePivot('role', 'owner')->exists();
    }
}
