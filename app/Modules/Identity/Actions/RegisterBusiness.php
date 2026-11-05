<?php

namespace App\Modules\Identity\Actions;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterBusiness
{
    public function handle(string $name, string $email, string $password, string $businessName): User
    {
        return DB::transaction(function () use ($name, $email, $password, $businessName) {
            $user = User::create(compact('name', 'email', 'password'));
            $tenant = Tenant::create(['name' => $businessName, 'timezone' => 'America/Sao_Paulo']);
            $user->tenants()->attach($tenant, ['role' => 'owner']);

            return $user;
        });
    }
}
