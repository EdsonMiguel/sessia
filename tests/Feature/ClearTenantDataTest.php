<?php

use App\Livewire\Platform\ClearData;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\ClearTenantData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function seedResetFixture(int $tenant, int $user): void
{
    $insert = fn (string $table, array $data) => DB::table($table)->insertGetId($data + [
        'tenant_id' => $tenant, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $customer = $insert('customers', ['name' => 'Cliente fictícia', 'phone' => '11999991234']);
    $address = $insert('customer_addresses', ['customer_id' => $customer, 'postal_code' => '01000-000',
        'street' => 'Rua Fictícia', 'number' => '10', 'neighborhood' => 'Centro', 'city' => 'Cidade Teste', 'state' => 'SP',
        'deleted_at' => now()]);
    $procedure = $insert('procedures', ['name' => 'Procedimento fictício', 'duration_minutes' => 60, 'default_price' => '100.00']);
    $insert('customer_procedure_prices', ['customer_id' => $customer, 'procedure_id' => $procedure, 'price' => '80.00']);
    $package = $insert('procedure_packages', ['name' => 'Pacote fictício', 'total' => '300.00']);
    $insert('procedure_package_items', ['procedure_package_id' => $package, 'procedure_id' => $procedure, 'quantity' => 3]);
    $contract = $insert('service_contracts', ['customer_id' => $customer, 'address_id' => $address,
        'type' => 'plan', 'total' => '300.00', 'starts_on' => '2026-11-01']);
    $item = $insert('contract_items', ['service_contract_id' => $contract, 'procedure_id' => $procedure,
        'procedure_name' => 'Procedimento fictício', 'duration_minutes' => 60, 'quantity' => 3, 'unit_price' => '100.00']);
    $insert('contract_events', ['service_contract_id' => $contract, 'user_id' => $user, 'event' => 'created']);
    $period = $insert('plan_periods', ['service_contract_id' => $contract, 'month' => '2026-11-01', 'due_on' => '2026-11-10', 'amount' => '300.00']);
    $periodItem = $insert('plan_period_items', ['plan_period_id' => $period, 'contract_item_id' => $item, 'quantity' => 3]);
    $appointment = $insert('appointments', ['customer_id' => $customer, 'address_snapshot' => '{}',
        'starts_at' => now(), 'ends_at' => now()->addHour(), 'occupied_from' => now(), 'occupied_until' => now()->addHour()]);
    $appointmentItem = $insert('appointment_items', ['appointment_id' => $appointment, 'procedure_id' => $procedure,
        'contract_item_id' => $item, 'plan_period_item_id' => $periodItem, 'procedure_name' => 'Procedimento fictício', 'duration_minutes' => 60]);
    $insert('appointment_events', ['appointment_id' => $appointment, 'user_id' => $user, 'event' => 'created']);
    $insert('schedule_blocks', ['starts_at' => now(), 'ends_at' => now()->addHour(), 'reason' => 'Teste', 'created_by' => $user]);
    $insert('service_executions', ['appointment_item_id' => $appointmentItem, 'contract_item_id' => $item,
        'plan_period_item_id' => $periodItem, 'performed_at' => now(), 'performed_by' => $user]);
    $receivable = $insert('receivables', ['customer_id' => $customer, 'service_contract_id' => $contract,
        'plan_period_id' => $period, 'source_key' => 'plan:'.$period, 'description' => 'Cobrança fictícia', 'amount' => '300.00']);
    $installment = $insert('installments', ['receivable_id' => $receivable, 'number' => 1, 'due_on' => '2026-11-10', 'amount' => '300.00']);
    $payment = $insert('payments', ['customer_id' => $customer, 'amount' => '100.00', 'paid_at' => now(),
        'method' => 'pix', 'idempotency_key' => (string) Str::uuid(), 'recorded_by' => $user]);
    $insert('payment_allocations', ['payment_id' => $payment, 'installment_id' => $installment, 'amount' => '100.00']);
}

beforeEach(function () {
    $this->withoutVite();
    $this->owner = User::factory()->create(['password' => Hash::make('senha-teste-123')]);
    $this->tenant = Tenant::factory()->create(['slug' => 'negocio-ficticio', 'pix_key' => 'pix@example.test']);
    $this->other = Tenant::factory()->create();
    $this->owner->tenants()->attach($this->tenant, ['role' => 'owner']);
    $this->owner->tenants()->attach($this->other, ['role' => 'owner']);
    $this->actingAs($this->owner);
    session(['current_tenant_id' => $this->tenant->id]);
    seedResetFixture($this->tenant->id, $this->owner->id);
    seedResetFixture($this->other->id, $this->owner->id);
});

it('clears all operational data only for the current business and preserves identity settings and an audit', function () {
    $this->get('/minha-conta')->assertOk()->assertSee('Limpar dados cadastrados');
    $this->get('/configuracoes')->assertOk()->assertDontSee('Limpar dados cadastrados');
    $settings = $this->tenant->fresh()->getAttributes();
    $otherRecords = [];
    foreach (ClearTenantData::TABLES as $table) {
        $otherRecords[$table] = DB::table($table)->where('tenant_id', $this->other->id)->get()->toJson();
    }
    Livewire::test(ClearData::class)->assertSee('Limpar dados cadastrados')
        ->set('clearPassword', 'senha-teste-123')->set('clearConfirmation', 'APAGAR TUDO')
        ->call('clearData')->assertHasNoErrors()->assertRedirect(route('account.show'));
    foreach (ClearTenantData::TABLES as $table) {
        expect(DB::table($table)->where('tenant_id', $this->tenant->id)->count())->toBe(0)
            ->and(DB::table($table)->where('tenant_id', $this->other->id)->get()->toJson())->toBe($otherRecords[$table]);
    }
    expect($this->tenant->fresh()->getAttributes())->toBe($settings)
        ->and($this->owner->fresh()->tenants()->count())->toBe(2);
    $this->assertAuthenticatedAs($this->owner);
    $audit = DB::table('tenant_data_resets')->sole();
    expect($audit->tenant_id)->toBe($this->tenant->id)->and($audit->user_id)->toBe($this->owner->id)
        ->and(json_decode($audit->deleted_counts, true))->toBe(array_fill_keys(ClearTenantData::TABLES, 1));
    $this->get('/inicio')->assertOk();
    $this->get('/configuracoes')->assertOk();
});

it('requires the exact confirmation and current password before any deletion', function (string $password, string $confirmation, string $error) {
    Livewire::test(ClearData::class)->set('clearPassword', $password)->set('clearConfirmation', $confirmation)
        ->call('clearData')->assertHasErrors($error)->assertSet('clearPassword', '');
    foreach (ClearTenantData::TABLES as $table) {
        expect(DB::table($table)->count())->toBe(2);
    }
    expect(DB::table('tenant_data_resets')->count())->toBe(0);
})->with([
    'wrong password' => ['errada', 'APAGAR TUDO', 'clearPassword'],
    'missing password' => ['', 'APAGAR TUDO', 'clearPassword'],
    'missing confirmation' => ['senha-teste-123', '', 'clearConfirmation'],
    'wrong confirmation' => ['senha-teste-123', 'apagar tudo', 'clearConfirmation'],
]);

it('rejects a changed session target even if the user owns both businesses', function () {
    expect(fn () => app(ClearTenantData::class)->handle($this->other->id, 'senha-teste-123', 'APAGAR TUDO'))
        ->toThrow(HttpException::class);
    expect(DB::table('customers')->count())->toBe(2);
});

it('rejects a revoked owner before deleting anything', function () {
    $component = Livewire::test(ClearData::class)->set('clearPassword', 'senha-teste-123')->set('clearConfirmation', 'APAGAR TUDO');
    $this->owner->tenants()->detach($this->tenant);
    $component->call('clearData')->assertForbidden();
    expect(DB::table('customers')->count())->toBe(2)->and(DB::table('tenant_data_resets')->count())->toBe(0);
});

it('rejects a member who is not an owner', function () {
    $this->owner->tenants()->updateExistingPivot($this->tenant->id, ['role' => 'member']);
    Livewire::test(ClearData::class)->assertForbidden();
    expect(fn () => app(ClearTenantData::class)->handle($this->tenant->id, 'senha-teste-123', 'APAGAR TUDO'))
        ->toThrow(AuthorizationException::class);
    expect(DB::table('customers')->count())->toBe(2);
});

it('rolls back the entire cleanup if a delete fails', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Injeção de falha com trigger específica do SQLite.');
    }
    DB::unprepared("CREATE TRIGGER fail_reset BEFORE DELETE ON procedures BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
    try {
        expect(fn () => app(ClearTenantData::class)->handle($this->tenant->id, 'senha-teste-123', 'APAGAR TUDO'))->toThrow(QueryException::class);
        foreach (ClearTenantData::TABLES as $table) {
            expect(DB::table($table)->count())->toBe(2);
        }
        expect(DB::table('tenant_data_resets')->count())->toBe(0);
    } finally {
        DB::unprepared('DROP TRIGGER fail_reset');
    }
});

it('limits repeated password attempts', function () {
    $component = Livewire::test(ClearData::class)->set('clearConfirmation', 'APAGAR TUDO');
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $component->set('clearPassword', 'errada')->call('clearData')->assertHasErrors('clearPassword');
    }
    $component->set('clearPassword', 'senha-teste-123')->call('clearData')->assertHasErrors('clearPassword');
    expect(DB::table('customers')->count())->toBe(2);
});
