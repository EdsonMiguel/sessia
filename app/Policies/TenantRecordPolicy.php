<?php

namespace App\Policies;

use App\Models\TenantRecord;
use App\Models\User;
use App\Modules\Platform\CurrentTenant;

class TenantRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return (new TenantPolicy)->update($user, app(CurrentTenant::class)->get());
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, TenantRecord $record): bool
    {
        return (int) $record->tenant_id === app(CurrentTenant::class)->get()->id && $this->viewAny($user);
    }

    public function update(User $user, TenantRecord $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, TenantRecord $record): bool
    {
        return $this->view($user, $record);
    }
}
