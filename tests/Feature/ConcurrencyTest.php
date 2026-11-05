<?php

use App\Models\Customer;
use App\Models\Procedure;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\IssueReceivable;
use App\Modules\Contract\CreateContract;
use App\Modules\Scheduling\ScheduleAppointment;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

function concurrentOperationFixtures(): array
{
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $owner->tenants()->attach($tenant, ['role' => 'owner']);
    session()->forget('current_tenant_id');
    test()->actingAs($owner);
    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create(['postal_code' => '01000-000', 'street' => 'Rua de Teste', 'number' => '1', 'neighborhood' => 'Centro', 'city' => 'Cidade de Teste', 'state' => 'SP']);
    $procedure = Procedure::factory()->create();
    $contract = app(CreateContract::class)->handle(['customer_id' => $customer->id, 'type' => 'single', 'total' => '100.00', 'starts_on' => '2026-01-01', 'items' => [['procedure_id' => $procedure->id, 'quantity' => 1, 'unit_price' => '100.00']]]);
    $appointment = app(ScheduleAppointment::class)->handle(['customer_id' => $customer->id, 'address_id' => $address->id, 'starts_at' => '2026-12-01T09:00', 'preparation_minutes' => 0, 'travel_minutes' => 0, 'items' => [['procedure_id' => $procedure->id, 'contract_item_id' => $contract->items()->sole()->id]]]);

    return compact('owner', 'contract', 'appointment');
}

function concurrentProcesses(int $ownerId, string $action, array $payloads): array
{
    $config = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => 'sessia_test', 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'] ?? '', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
    // RefreshDatabase wraps each test in a transaction. Commit only this
    // disposable fixture so independent workers can see it, then force the next
    // test to rebuild its disposable schema instead of retaining these records.
    DB::commit();
    RefreshDatabaseState::$migrated = false;
    $processes = [];
    foreach ($payloads as $payload) {
        $process = new Process([PHP_BINARY, base_path('tests/Support/operation-worker.php'), (string) $ownerId, $action, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))], base_path(), $environment, timeout: 30);
        $process->start();
        $processes[] = $process;
    }
    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        $results[] = trim($process->getOutput());
    }
    sort($results);

    return $results;
}

beforeEach(function () {
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sessia_test') {
        $this->markTestSkipped('Concorrência real exige o MySQL descartável sessia_test.');
    }
});

it('completes the same appointment concurrently without duplicate consumption', function () {
    $f = concurrentOperationFixtures();
    expect(concurrentProcesses($f['owner']->id, 'complete', [['id' => $f['appointment']->id], ['id' => $f['appointment']->id]]))->toBe(['success', 'success']);
    expect(DB::table('service_executions')->count())->toBe(1);
});

it('rejects concurrent consumption of unavailable balance from competing visits', function () {
    $f = concurrentOperationFixtures();
    // Simulate stale/legacy reservations to test the consumption guard itself.
    $copy = $f['appointment']->replicate();
    $copy->save();
    $copy->items()->create($f['appointment']->items()->sole()->only(['procedure_id', 'contract_item_id', 'procedure_name', 'duration_minutes']));
    expect(concurrentProcesses($f['owner']->id, 'complete', [['id' => $f['appointment']->id], ['id' => $copy->id]]))->toBe(['rejected', 'success']);
    expect(DB::table('service_executions')->count())->toBe(1);
});

it('rejects concurrent overpayment of the same installment', function () {
    $f = concurrentOperationFixtures();
    $i = app(IssueReceivable::class)->handle($f['contract']->id, ['2026-12-10'])->installments()->sole();
    $paidAt = now('America/Sao_Paulo')->subMinute()->format('Y-m-d\TH:i');
    expect(concurrentProcesses($f['owner']->id, 'pay', [
        ['allocations' => [$i->id => '100.00'], 'paidAt' => $paidAt, 'key' => (string) Str::uuid()],
        ['allocations' => [$i->id => '100.00'], 'paidAt' => $paidAt, 'key' => (string) Str::uuid()],
    ]))->toBe(['rejected', 'success']);
    expect(DB::table('payments')->count())->toBe(1);
});
