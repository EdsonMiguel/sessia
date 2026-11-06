<?php

namespace App\Modules\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClearTenantData
{
    // Explicit dependency order; never truncate or disable foreign key checks.
    public const TABLES = [
        'payment_allocations', 'payments', 'installments', 'receivables',
        'service_executions', 'schedule_blocks', 'appointment_events',
        'appointment_items', 'appointments', 'plan_period_items', 'plan_periods',
        'contract_events', 'contract_items', 'service_contracts',
        'procedure_package_items', 'procedure_packages', 'customer_procedure_prices',
        'customer_addresses', 'customers', 'procedures',
    ];

    public function handle(int $expectedTenantId, #[\SensitiveParameter] string $password, string $confirmation): void
    {
        $tenant = app(CurrentTenant::class)->get();
        Gate::authorize('update', $tenant);
        // The rendered target must still match the trusted session context.
        abort_unless($tenant->id === $expectedTenantId, 403, 'O negócio selecionado mudou. Reabra as configurações.');
        Validator::make(['clearPassword' => $password, 'clearConfirmation' => $confirmation], [
            'clearPassword' => ['required', 'string', 'current_password:web'],
            'clearConfirmation' => ['required', Rule::in(['APAGAR TUDO'])],
        ], [
            'clearPassword.required' => 'Informe sua senha atual.',
            'clearPassword.current_password' => 'A senha atual não confere.',
            'clearConfirmation.required' => 'Digite APAGAR TUDO para confirmar.',
            'clearConfirmation.in' => 'Digite exatamente APAGAR TUDO para confirmar.',
        ])->validate();

        TenantTransaction::run(function ($tenant) {
            Gate::authorize('update', $tenant);
            $counts = [];
            foreach (self::TABLES as $table) {
                $counts[$table] = DB::table($table)->where('tenant_id', $tenant->id)->delete();
            }
            DB::table('tenant_data_resets')->insert([
                'tenant_id' => $tenant->id, 'user_id' => auth()->id(),
                'deleted_counts' => json_encode($counts, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
        });
    }
}
