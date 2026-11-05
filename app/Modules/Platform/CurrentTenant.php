<?php

namespace App\Modules\Platform;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class CurrentTenant
{
    private ?Tenant $jobTenant = null;

    public function forJob(Tenant $tenant, \Closure $callback): mixed
    {
        abort_unless(app()->runningInConsole() && ! Auth::check(), 403);
        $previous = $this->jobTenant;
        $this->jobTenant = $tenant;
        try {
            return $callback();
        } finally {
            $this->jobTenant = $previous;
        }
    }

    public function isJob(): bool
    {
        return $this->jobTenant !== null;
    }

    public function get(): Tenant
    {
        if ($this->jobTenant !== null) {
            return $this->jobTenant;
        }
        $user = Auth::user();
        abort_unless($user, 403, 'Entre na sua conta para acessar o negócio.');

        // Only a server-side session value is considered, and membership is
        // checked every time. Never retain context across requests or jobs.
        $id = session('current_tenant_id');
        $query = $user->tenants();
        $tenant = $id === null
            ? $query->orderBy('tenants.id')->first()
            : $query->where('tenants.id', $id)->first();

        abort_unless($tenant, 403, 'Seu acesso a este negócio não está disponível.');
        session(['current_tenant_id' => $tenant->getKey()]);

        return $tenant;
    }
}
