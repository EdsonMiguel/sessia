<?php

use App\Livewire\Billing\ReceivableDetail;
use App\Livewire\Contract\ContractDetail;
use App\Livewire\Contract\ContractForm;
use App\Livewire\Platform\BusinessSettings;
use App\Livewire\Scheduling\Agenda;
use App\Livewire\Scheduling\AppointmentDetail;
use App\Livewire\Scheduling\AppointmentForm;
use App\Models\Appointment;
use App\Models\ContractItem;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PlanPeriod;
use App\Models\Procedure;
use App\Models\Receivable;
use App\Models\ServiceContract;
use App\Models\ServiceExecution;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Attendance\CompleteAttendance;
use App\Modules\Billing\IssueReceivable;
use App\Modules\Billing\RecordPayment;
use App\Modules\Contract\CreateContract;
use App\Modules\Contract\GeneratePlanHorizon;
use App\Modules\Contract\GeneratePlanPeriod;
use App\Modules\Contract\GenerateRecurringAppointments;
use App\Modules\Contract\SessionBalance;
use App\Modules\Platform\CurrentTenant;
use App\Modules\Scheduling\ScheduleAppointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->withoutVite();
    app()->setLocale('pt_BR');
    $this->travelTo(CarbonImmutable::parse('2026-12-01 06:00:00', 'UTC'));
    $this->owner = User::factory()->create();
    $this->tenant = Tenant::factory()->create();
    $this->owner->tenants()->attach($this->tenant, ['role' => 'owner']);
    session()->forget('current_tenant_id');
    $this->actingAs($this->owner);
    $this->customer = Customer::factory()->create();
    $this->address = $this->customer->addresses()->create(['postal_code' => '01000-000', 'street' => 'Rua Fictícia', 'number' => '10', 'neighborhood' => 'Centro', 'city' => 'Cidade de Teste', 'state' => 'SP', 'is_primary' => true]);
    $this->procedure = Procedure::factory()->create(['name' => 'Drenagem de teste', 'duration_minutes' => 60, 'default_price' => '100.00']);
});

function operationContract(array $overrides = []): ServiceContract
{
    $test = test();

    return app(CreateContract::class)->handle(array_replace([
        'customer_id' => $test->customer->id, 'type' => 'package', 'total' => '100.00',
        'starts_on' => '2026-12-01', 'ends_on' => null, 'weekday' => 2, 'preferred_time' => '09:00',
        'due_day' => 10, 'address_id' => $test->address->id,
        'items' => [['procedure_id' => $test->procedure->id, 'quantity' => 1, 'unit_price' => '100.00']],
    ], $overrides));
}

function operationAppointment(?ContractItem $item = null, string $at = '2026-12-01T09:00', array $overrides = []): Appointment
{
    $test = test();

    return app(ScheduleAppointment::class)->handle(array_replace([
        'customer_id' => $test->customer->id, 'address_id' => $test->address->id,
        'starts_at' => $at, 'preparation_minutes' => 0, 'travel_minutes' => 0,
        'items' => [['procedure_id' => $test->procedure->id, 'contract_item_id' => $item?->id]],
    ], $overrides));
}

it('renders the remaining modules with real records', function () {
    $contract = operationContract(['type' => 'single']);
    $appointment = operationAppointment($contract->items()->sole());
    $charge = app(IssueReceivable::class)->handle($contract->id, ['2026-12-10']);
    foreach (['/inicio', '/agenda', '/agenda/novo', '/agenda/'.$appointment->id, '/agenda/'.$appointment->id.'/editar', '/contratacoes', '/contratacoes/nova', '/contratacoes/'.$contract->id, '/financeiro', '/financeiro/'.$charge->id] as $url) {
        $this->get($url)->assertOk();
    }
});

it('preserves negotiated price and procedure snapshots after catalog and habitual changes', function () {
    $this->customer->procedurePrices()->create(['procedure_id' => $this->procedure->id, 'price' => '85.00']);
    $contract = operationContract(['items' => [['procedure_id' => $this->procedure->id, 'quantity' => 1, 'unit_price' => '']]]);
    expect($contract->items()->sole()->unit_price)->toBe('85.00');
    $this->procedure->update(['default_price' => '150.00', 'name' => 'Novo nome', 'duration_minutes' => 90]);
    $this->customer->procedurePrices()->first()->update(['price' => '90.00']);
    expect($contract->items()->sole()->unit_price)->toBe('85.00')->and($contract->items()->sole()->procedure_name)->toBe('Drenagem de teste');
    $appointment = operationAppointment($contract->items()->sole());
    expect($appointment->items()->sole()->duration_minutes)->toBe(60)->and($appointment->items()->sole()->procedure_name)->toBe('Drenagem de teste');
});

it('suggests habitual prices and persists an explicitly negotiated contract through Livewire', function () {
    $this->customer->procedurePrices()->create(['procedure_id' => $this->procedure->id, 'price' => '85.00']);
    Livewire::test(ContractForm::class)->set('customer_id', (string) $this->customer->id)
        ->set('items.0.procedure_id', (string) $this->procedure->id)->assertSet('items.0.unit_price', '85.00')
        ->set('items.0.unit_price', '80,00')->set('total', '75,00')->call('save')->assertHasNoErrors();
    expect(ServiceContract::sole()->total)->toBe('75.00')->and(ContractItem::sole()->unit_price)->toBe('80.00');
});

it('detects conflicts including preparation and travel but permits adjacent occupancy', function () {
    operationAppointment(at: '2026-12-01T10:00', overrides: ['preparation_minutes' => 15, 'travel_minutes' => 30]);
    expect(fn () => operationAppointment(at: '2026-12-01T09:00'))->toThrow(ValidationException::class);
    expect(fn () => operationAppointment(at: '2026-12-01T11:00'))->toThrow(ValidationException::class);
    operationAppointment(at: '2026-12-01T11:30');
    expect(Appointment::count())->toBe(2);
});

it('rejects conflicting blocks and preserves removed blocks', function () {
    $block = app(ScheduleAppointment::class)->block('2026-12-01T10:00', '2026-12-01T12:00', 'Compromisso pessoal');
    expect(fn () => operationAppointment(at: '2026-12-01T11:00'))->toThrow(ValidationException::class);
    app(ScheduleAppointment::class)->unblock($block->id);
    operationAppointment(at: '2026-12-01T11:00');
    expect($block->fresh()->cancelled_at)->not->toBeNull();
    expect(fn () => app(ScheduleAppointment::class)->block('2026-12-01T11:00', '2026-12-01T12:00', 'Conflito'))->toThrow(ValidationException::class);
});

it('stores UTC instants and immutable address snapshots and rescheduling history', function () {
    $appointment = operationAppointment();
    expect($appointment->starts_at->format('H:i'))->toBe('12:00');
    $this->address->update(['street' => 'Rua alterada']);
    expect($appointment->fresh()->address_snapshot['street'])->toBe('Rua Fictícia');
    $data = ['customer_id' => $this->customer->id, 'address_id' => $this->address->id, 'starts_at' => '2026-12-02T14:00', 'preparation_minutes' => 0, 'travel_minutes' => 0, 'items' => [['procedure_id' => $this->procedure->id]]];
    app(ScheduleAppointment::class)->handle($data, $appointment->id);
    expect($appointment->events()->count())->toBe(2);
    expect($appointment->events()->where('event', 'rescheduled')->sole()->before['address_snapshot']['street'])->toBe('Rua Fictícia');
});

it('reserves without consumption completes once and keeps package finances separate', function () {
    $contract = operationContract();
    $item = $contract->items()->sole();
    $a = operationAppointment($item);
    expect(SessionBalance::for($item))->toMatchArray(['performed' => 0, 'reserved' => 1, 'available' => 0]);
    expect(fn () => operationAppointment($item, '2026-12-02T09:00'))->toThrow(ValidationException::class);
    app(CompleteAttendance::class)->handle($a->id);
    app(CompleteAttendance::class)->handle($a->id);
    expect(ServiceExecution::count())->toBe(1)->and(SessionBalance::for($item))->toMatchArray(['performed' => 1, 'reserved' => 0]);
    expect($contract->fresh()->status)->toBe('completed')->and(Payment::count())->toBe(0)->and(Receivable::count())->toBe(0);
    $charge = app(IssueReceivable::class)->handle($contract->id, ['2026-12-10']);
    expect($charge->installments()->sole()->remainingCents())->toBe(10000);
});

it('does not release a package charge before operational completion', function () {
    $contract = operationContract();
    expect(fn () => app(IssueReceivable::class)->handle($contract->id, ['2026-12-10']))->toThrow(ValidationException::class);
    expect(Receivable::count())->toBe(0);
});

it('tracks independent balances for mixed procedures', function () {
    $other = Procedure::factory()->create(['name' => 'Limpeza de teste']);
    $contract = operationContract(['total' => '350.00', 'items' => [
        ['procedure_id' => $this->procedure->id, 'quantity' => 2, 'unit_price' => '100.00'],
        ['procedure_id' => $other->id, 'quantity' => 1, 'unit_price' => '120.00'],
    ]]);
    [$first, $second] = $contract->items()->orderBy('id')->get()->all();
    $a = operationAppointment($first, overrides: ['items' => [
        ['procedure_id' => $first->procedure_id, 'contract_item_id' => $first->id],
        ['procedure_id' => $second->procedure_id, 'contract_item_id' => $second->id],
    ]]);
    app(CompleteAttendance::class)->handle($a->id);
    expect(SessionBalance::for($first)['available'])->toBe(1)->and(SessionBalance::for($second)['available'])->toBe(0);
    expect($contract->fresh()->status)->toBe('active');
});

it('releases reservations for cancellations and no shows without consuming a session', function (string $status) {
    $contract = operationContract();
    $item = $contract->items()->sole();
    $a = operationAppointment($item);
    app(ScheduleAppointment::class)->changeStatus($a->id, $status, 'Motivo de teste');
    expect(SessionBalance::for($item))->toMatchArray(['available' => 1, 'performed' => 0, 'reserved' => 0]);
    expect($a->events()->where('event', $status)->sole()->user_id)->toBe($this->owner->id);
})->with(['cancelled', 'no_show']);

it('reverses execution auditably and restores balance without modifying a released charge', function () {
    $contract = operationContract();
    $item = $contract->items()->sole();
    $a = operationAppointment($item);
    app(CompleteAttendance::class)->handle($a->id);
    $charge = app(IssueReceivable::class)->handle($contract->id, ['2026-12-10']);
    app(CompleteAttendance::class)->reverse($a->id, 'Registro incorreto');
    app(CompleteAttendance::class)->reverse($a->id, 'Registro incorreto');
    expect(SessionBalance::for($item)['available'])->toBe(1)->and($a->fresh()->status)->toBe('cancelled')->and($contract->fresh()->status)->toBe('active');
    expect(ServiceExecution::sole()->reversal_reason)->toBe('Registro incorreto');
    expect($charge->fresh()->amount)->toBe('100.00');
});

it('limits a four session plan to four Tuesdays in a five Tuesday month', function () {
    $contract = operationContract(['type' => 'plan', 'total' => '400.00', 'items' => [['procedure_id' => $this->procedure->id, 'quantity' => 4, 'unit_price' => '100.00']]]);
    $period = app(GeneratePlanPeriod::class)->handle($contract->id, '2026-12');
    app(GenerateRecurringAppointments::class)->handle($period->id);
    app(GenerateRecurringAppointments::class)->handle($period->id);
    expect(Appointment::count())->toBe(4)->and($period->items()->sole()->quantity)->toBe(4);
    expect(Appointment::whereDate('starts_at', '2026-12-29')->count())->toBe(0);
    $this->get('/contratacoes/'.$contract->id)->assertOk()->assertSee('12/2026');
});

it('generates periods schedules and monthly charges idempotently and independently', function () {
    $contract = operationContract(['type' => 'plan', 'items' => [['procedure_id' => $this->procedure->id, 'quantity' => 4]]]);
    app(ScheduleAppointment::class)->block('2026-12-01T08:00', '2026-12-01T11:00', 'Indisponível');
    $messages = app(GeneratePlanHorizon::class)->handle($contract->id, 1);
    app(GeneratePlanHorizon::class)->handle($contract->id, 1);
    expect(PlanPeriod::count())->toBe(1)->and(Receivable::count())->toBe(1)->and(Appointment::count())->toBe(3);
    expect($messages)->not->toBeEmpty();
    expect(ServiceExecution::count())->toBe(0)->and(Payment::count())->toBe(0);
});

it('requires explicit terms for partial months instead of inventing prorating', function () {
    $contract = operationContract(['type' => 'plan', 'starts_on' => '2026-12-15']);
    expect(fn () => app(GeneratePlanPeriod::class)->handle($contract->id, '2026-12'))->toThrow(ValidationException::class);
    expect(PlanPeriod::count())->toBe(0);
    $item = $contract->items()->sole();
    $period = app(GeneratePlanPeriod::class)->handle($contract->id, '2026-12', ['confirmed' => true, 'amount' => '60.00', 'quantities' => [$item->id => 1]]);
    expect($period->amount)->toBe('60.00')->and($period->items()->sole()->quantity)->toBe(1);
});

it('does not create new periods when paused and preserves previous charges and reservations', function () {
    $contract = operationContract(['type' => 'plan']);
    app(GeneratePlanHorizon::class)->handle($contract->id, 1);
    app(CreateContract::class)->changeStatus($contract->id, 'paused', 'Pausa negociada');
    app(GeneratePlanHorizon::class)->handle($contract->id, 2);
    expect(PlanPeriod::count())->toBe(1)->and(Receivable::count())->toBe(1)->and(Appointment::count())->toBe(1);
});

it('does not transfer plan balance into another month', function () {
    $contract = operationContract(['type' => 'plan']);
    $period = app(GeneratePlanPeriod::class)->handle($contract->id, '2026-12');
    $item = $period->items()->sole();
    expect(fn () => operationAppointment($contract->items()->sole(), '2027-01-05T09:00', ['items' => [['procedure_id' => $this->procedure->id, 'contract_item_id' => $item->contract_item_id, 'plan_period_item_id' => $item->id]]]))->toThrow(ValidationException::class);
});

it('preserves installment total and partial allocations without floating point', function () {
    $contract = operationContract(['type' => 'single', 'total' => '100.00']);
    $charge = app(IssueReceivable::class)->handle($contract->id, ['2026-12-01', '2027-01-01', '2027-02-01']);
    $items = $charge->installments()->get();
    expect($items->pluck('amount')->all())->toBe(['33.34', '33.33', '33.33']);
    $key = (string) Str::uuid();
    $allocations = [$items[0]->id => '10.00', $items[1]->id => '3.33'];
    app(RecordPayment::class)->handle($allocations, 'pix', '2026-12-01T02:00', $key);
    app(RecordPayment::class)->handle($allocations, 'pix', '2026-12-01T02:00', $key);
    expect(Payment::count())->toBe(1)->and(Payment::sole()->amount)->toBe('13.33');
    expect($items[0]->fresh()->remainingCents())->toBe(2334)->and($items[0]->fresh()->status())->toBe('partial');
    expect(app(IssueReceivable::class)->handle($contract->id, ['2026-12-01', '2027-01-01', '2027-02-01'])->id)->toBe($charge->id);
});

it('rejects overpayment and reusing a payment key with different data', function () {
    $contract = operationContract(['type' => 'single']);
    $i = app(IssueReceivable::class)->handle($contract->id, ['2026-12-10'])->installments()->sole();
    expect(fn () => app(RecordPayment::class)->handle([$i->id => '100.01'], 'pix', '2026-12-01T02:00', (string) Str::uuid()))->toThrow(ValidationException::class);
    $key = (string) Str::uuid();
    app(RecordPayment::class)->handle([$i->id => '50.00'], 'pix', '2026-12-01T02:00', $key);
    expect(fn () => app(RecordPayment::class)->handle([$i->id => '40.00'], 'pix', '2026-12-01T02:00', $key))->toThrow(ValidationException::class);
});

it('reverses a payment without altering paid installment values or deleting history', function () {
    $contract = operationContract(['type' => 'single']);
    $i = app(IssueReceivable::class)->handle($contract->id, ['2026-12-10'])->installments()->sole();
    $payment = app(RecordPayment::class)->handle([$i->id => '100.00'], 'cash', '2026-12-01T02:00', (string) Str::uuid());
    expect($i->fresh()->status())->toBe('paid');
    app(RecordPayment::class)->reverse($payment->id, 'Pagamento lançado incorretamente');
    expect($i->fresh()->remainingCents())->toBe(10000)->and($i->fresh()->amount)->toBe('100.00');
    expect($payment->fresh()->allocations()->count())->toBe(1)->and($payment->fresh()->reversed_by)->toBe($this->owner->id);
});

it('records receipts through Livewire and shows actual dashboard figures', function () {
    $contract = operationContract(['type' => 'single']);
    $charge = app(IssueReceivable::class)->handle($contract->id, ['2026-12-01']);
    $i = $charge->installments()->sole();
    Livewire::test(ReceivableDetail::class, ['receivableId' => $charge->id])->set('allocations.'.$i->id, '40,00')->set('paidAt', '2026-12-01T02:00')->call('receive')->assertHasNoErrors();
    $this->get('/inicio')->assertOk()->assertSee('R$ 40,00')->assertSee('R$ 60,00');
    $this->get('/financeiro')->assertOk()->assertSee('Parcial');
});

it('configures tenant pix data without confirming payments', function () {
    Livewire::test(BusinessSettings::class)->set('pix_key', 'recebimento@example.test')->set('pix_receiver', 'Negócio de Teste')->set('pix_city', 'Cidade de Teste')->call('save')->assertHasNoErrors();
    $contract = operationContract(['type' => 'single']);
    $charge = app(IssueReceivable::class)->handle($contract->id, ['2026-12-10']);
    $this->get('/financeiro/'.$charge->id)->assertOk()->assertSee('recebimento@example.test');
    expect(Payment::count())->toBe(0);
});

it('runs recurring generation with a trusted tenant context and clears it after every job', function () {
    $contract = operationContract(['type' => 'plan']);
    Auth::logout();
    session()->forget('current_tenant_id');
    $this->artisan('sessia:generate-plans', ['--months' => 1])->assertSuccessful();
    $this->artisan('sessia:generate-plans', ['--months' => 1])->assertSuccessful();
    expect(DB::table('plan_periods')->count())->toBe(1)->and(DB::table('receivables')->count())->toBe(1);
    expect(fn () => app(CurrentTenant::class)->get())->toThrow(HttpException::class);
    expect(fn () => app(CurrentTenant::class)->forJob($this->tenant, fn () => throw new RuntimeException('test')))->toThrow(RuntimeException::class);
    expect(app(CurrentTenant::class)->isJob())->toBeFalse();
});

function foreignOperations(): array
{
    $previous = Auth::user();
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $owner->tenants()->attach($tenant, ['role' => 'owner']);
    Auth::login($owner);
    session()->forget('current_tenant_id');
    $customer = Customer::factory()->create();
    $procedure = Procedure::factory()->create();
    $address = $customer->addresses()->create(['postal_code' => '01000-000', 'street' => 'Rua de outro negócio', 'number' => '2', 'neighborhood' => 'Centro', 'city' => 'Cidade Fictícia', 'state' => 'SP']);
    $input = ['customer_id' => $customer->id, 'type' => 'single', 'total' => '120.00', 'starts_on' => '2026-12-01', 'items' => [['procedure_id' => $procedure->id, 'quantity' => 1]], 'weekday' => 2, 'preferred_time' => '09:00', 'due_day' => 10, 'address_id' => $address->id];
    $contract = app(CreateContract::class)->handle($input);
    $appointment = app(ScheduleAppointment::class)->handle(['customer_id' => $customer->id, 'address_id' => $address->id, 'starts_at' => '2026-12-01T09:00', 'preparation_minutes' => 0, 'travel_minutes' => 0, 'items' => [['procedure_id' => $procedure->id, 'contract_item_id' => $contract->items()->sole()->id]]]);
    $receivable = app(IssueReceivable::class)->handle($contract->id, ['2026-12-10']);
    $installment = $receivable->installments()->sole();
    $payment = app(RecordPayment::class)->handle([$installment->id => '10.00'], 'pix', '2026-12-01T02:00', (string) Str::uuid());
    $plan = app(CreateContract::class)->handle(array_replace($input, ['type' => 'plan']));
    $period = app(GeneratePlanPeriod::class)->handle($plan->id, '2026-12');
    Auth::login($previous);
    session()->forget('current_tenant_id');

    return compact('tenant', 'customer', 'procedure', 'address', 'contract', 'appointment', 'receivable', 'installment', 'payment', 'plan', 'period');
}

it('blocks foreign contracts appointments charges payments and periods on all new paths', function () {
    $foreign = foreignOperations();
    foreach (['/contratacoes/'.$foreign['contract']->id, '/agenda/'.$foreign['appointment']->id, '/agenda/'.$foreign['appointment']->id.'/editar', '/financeiro/'.$foreign['receivable']->id] as $url) {
        $this->get($url)->assertNotFound();
    }
    expect(fn () => app(CompleteAttendance::class)->handle($foreign['appointment']->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(CompleteAttendance::class)->reverse($foreign['appointment']->id, 'Tentativa'))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(ScheduleAppointment::class)->changeStatus($foreign['appointment']->id, 'cancelled', 'Tentativa'))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(IssueReceivable::class)->handle($foreign['contract']->id, ['2026-12-10']))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(RecordPayment::class)->handle([$foreign['installment']->id => '10.00'], 'pix', '2026-12-01T02:00', (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(RecordPayment::class)->reverse($foreign['payment']->id, 'Tentativa'))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(GeneratePlanPeriod::class)->handle($foreign['plan']->id, '2027-01'))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(GenerateRecurringAppointments::class)->handle($foreign['period']->id))->toThrow(ModelNotFoundException::class);
    $this->get('/financeiro')->assertDontSee('Rua de outro negócio')->assertDontSee($foreign['customer']->name);
    expect(DB::table('appointments')->where('id', $foreign['appointment']->id)->value('status'))->toBe('scheduled');
});

it('rejects foreign associations when creating contracts and visits', function () {
    $foreign = foreignOperations();
    expect(fn () => operationContract(['customer_id' => $foreign['customer']->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => operationContract(['items' => [['procedure_id' => $foreign['procedure']->id, 'quantity' => 1]]]))->toThrow(ModelNotFoundException::class);
    expect(fn () => operationAppointment(overrides: ['address_id' => $foreign['address']->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => operationAppointment(overrides: ['items' => [['procedure_id' => $foreign['procedure']->id]]]))->toThrow(ModelNotFoundException::class);
    expect(fn () => operationAppointment(overrides: ['items' => [['procedure_id' => $this->procedure->id, 'contract_item_id' => $foreign['contract']->items()->withoutGlobalScopes()->first()->id]]]))->toThrow(ModelNotFoundException::class);
});

it('does not attach another customers contract even within the same tenant', function () {
    $other = Customer::factory()->create();
    $contract = operationContract(['customer_id' => $other->id]);
    expect(fn () => operationAppointment($contract->items()->sole()))->toThrow(ValidationException::class);
});

it('keeps separate competent sessions and financial status in plans', function () {
    $contract = operationContract(['type' => 'plan']);
    $period = app(GeneratePlanPeriod::class)->handle($contract->id, '2026-12');
    $charge = app(IssueReceivable::class)->handle($contract->id, [$period->due_on->toDateString()], $period->id);
    $i = $charge->installments()->sole();
    app(RecordPayment::class)->handle([$i->id => '100.00'], 'pix', '2026-12-01T02:00', (string) Str::uuid());
    expect(ServiceExecution::count())->toBe(0)->and($i->fresh()->status())->toBe('paid');
    app(GenerateRecurringAppointments::class)->handle($period->id);
    app(CompleteAttendance::class)->handle(Appointment::sole()->id);
    expect($period->items()->sole()->activeExecutions()->count())->toBe(1)->and($contract->fresh()->status)->toBe('active');
});

it('allows execution with an unpaid monthly charge', function () {
    $contract = operationContract(['type' => 'plan']);
    app(GeneratePlanHorizon::class)->handle($contract->id, 1);
    app(CompleteAttendance::class)->handle(Appointment::sole()->id);
    expect(ServiceExecution::count())->toBe(1)->and(Payment::count())->toBe(0)->and(Installment::sole()->remainingCents())->toBe(10000);
});

it('does not recreate a cancelled recurring occurrence', function () {
    $contract = operationContract(['type' => 'plan']);
    app(GeneratePlanHorizon::class)->handle($contract->id, 1);
    app(ScheduleAppointment::class)->changeStatus(Appointment::sole()->id, 'cancelled', 'Cancelamento solicitado');
    app(GeneratePlanHorizon::class)->handle($contract->id, 1);
    expect(Appointment::count())->toBe(1)->and(Appointment::sole()->status)->toBe('cancelled');
});

it('shows linked operational history and balances on the customer profile', function () {
    $contract = operationContract(['type' => 'single']);
    operationAppointment($contract->items()->sole());
    app(IssueReceivable::class)->handle($contract->id, ['2026-12-10']);
    $this->get('/clientes/'.$this->customer->id)->assertOk()->assertSee('Contratações e sessões')->assertSee('reservadas')->assertSee('Histórico de agendamentos')->assertSee('R$ 100,00');
});

it('uses the tenants timezone when determining overdue installments', function () {
    $contract = operationContract(['type' => 'single']);
    $i = app(IssueReceivable::class)->handle($contract->id, ['2026-12-01'])->installments()->sole();
    $this->travelTo(CarbonImmutable::parse('2026-12-02 01:00:00', 'UTC'));
    expect($i->overdue())->toBeFalse();
    $this->travelTo(CarbonImmutable::parse('2026-12-02 04:00:00', 'UTC'));
    expect($i->overdue())->toBeTrue();
});

it('persists an appointment and its reserved session through the form', function () {
    $contract = operationContract();
    $item = $contract->items()->sole();
    Livewire::test(AppointmentForm::class)->set('customer_id', (string) $this->customer->id)
        ->set('address_id', (string) $this->address->id)->set('items.0.source', 'contract:'.$item->id)
        ->set('starts_at', '2026-12-01T10:00')->call('save')->assertHasNoErrors();
    expect(Appointment::count())->toBe(1)->and(SessionBalance::for($item)['reserved'])->toBe(1);
});

it('does not allow reallocating a payment to another customer', function () {
    $a = app(IssueReceivable::class)->handle(operationContract(['type' => 'single'])->id, ['2026-12-10'])->installments()->sole();
    $other = Customer::factory()->create();
    $b = app(IssueReceivable::class)->handle(operationContract(['type' => 'single', 'customer_id' => $other->id])->id, ['2026-12-10'])->installments()->sole();
    expect(fn () => app(RecordPayment::class)->handle([$a->id => '10.00', $b->id => '10.00'], 'pix', '2026-12-01T02:00', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(Payment::count())->toBe(0);
});

it('completes the full manual workflow through Livewire actions', function () {
    $contract = operationContract(['type' => 'single']);
    $appointment = operationAppointment($contract->items()->sole());
    $component = Livewire::test(AppointmentDetail::class, ['appointmentId' => $appointment->id]);
    $component->call('changeState', 'confirmed')->assertHasNoErrors();
    $component->call('complete')->assertHasNoErrors();
    expect($appointment->fresh()->status)->toBe('completed');
    Livewire::test(ContractDetail::class, ['contractId' => $contract->id])
        ->set('dueDates', ['2026-12-10', '2027-01-10'])->call('issueCharge')->assertHasNoErrors();
    $charge = Receivable::sole();
    $i = $charge->installments()->first();
    Livewire::test(ReceivableDetail::class, ['receivableId' => $charge->id])
        ->set('allocations.'.$i->id, '25,00')->set('paidAt', '2026-12-01T02:00')->call('receive')->assertHasNoErrors();
    Livewire::test(ReceivableDetail::class, ['receivableId' => $charge->id])
        ->set('reason', 'Correção de teste')->call('reverse', Payment::sole()->id)->assertHasNoErrors();
    expect($i->fresh()->remainingCents())->toBe(5000);
});

it('creates a period agenda and monthly charge separately through Livewire', function () {
    $contract = operationContract(['type' => 'plan']);
    $component = Livewire::test(ContractDetail::class, ['contractId' => $contract->id]);
    $component->set('month', '2026-12')->call('createPeriod')->assertHasNoErrors();
    expect(Receivable::count())->toBe(0)->and(Appointment::count())->toBe(0);
    $period = PlanPeriod::sole();
    $component->call('schedulePeriod', $period->id)->assertHasNoErrors();
    expect(Appointment::count())->toBe(1)->and(Receivable::count())->toBe(0);
    $component->call('billPeriod', $period->id)->assertHasNoErrors();
    expect(Receivable::count())->toBe(1);
});

it('rolls back the entire attendance when one of its procedures lacks balance', function () {
    $secondProcedure = Procedure::factory()->create();
    $contract = operationContract(['items' => [
        ['procedure_id' => $this->procedure->id, 'quantity' => 1],
        ['procedure_id' => $secondProcedure->id, 'quantity' => 1],
    ]]);
    [$first, $second] = $contract->items()->get()->all();
    $appointment = operationAppointment($first, overrides: ['items' => [
        ['procedure_id' => $first->procedure_id, 'contract_item_id' => $first->id],
        ['procedure_id' => $second->procedure_id, 'contract_item_id' => $second->id],
    ]]);
    // Existing corrected/imported execution exhausts only the second item.
    $legacy = $appointment->replicate();
    $legacy->status = 'completed';
    $legacy->save();
    $legacyItem = $legacy->items()->create(['procedure_id' => $second->procedure_id, 'contract_item_id' => $second->id, 'procedure_name' => $second->procedure_name, 'duration_minutes' => $second->duration_minutes]);
    ServiceExecution::create(['appointment_item_id' => $legacyItem->id, 'contract_item_id' => $second->id, 'performed_at' => now(), 'performed_by' => $this->owner->id]);
    expect(fn () => app(CompleteAttendance::class)->handle($appointment->id))->toThrow(ValidationException::class);
    expect($first->activeExecutions()->count())->toBe(0)->and(ServiceExecution::count())->toBe(1)->and($appointment->fresh()->status)->toBe('scheduled');
});

it('enforces foreign tenant integrity for billing rows even on direct database inserts', function () {
    $foreign = foreignOperations();
    expect(fn () => DB::table('installments')->insert(['tenant_id' => $this->tenant->id, 'receivable_id' => $foreign['receivable']->id, 'number' => 2, 'due_on' => '2026-12-20', 'amount' => '10.00']))->toThrow(QueryException::class);
});

it('isolates scheduled generation across multiple tenants', function () {
    foreignOperations();
    operationContract(['type' => 'plan']);
    Auth::logout();
    session()->forget('current_tenant_id');
    $this->artisan('sessia:generate-plans', ['--months' => 1])->assertSuccessful();
    expect(DB::table('plan_periods')->count())->toBe(2);
    expect(DB::table('receivables')->where('source_key', 'like', 'period:%')->count())->toBe(2);
    expect(app(CurrentTenant::class)->isJob())->toBeFalse();
});

it('renders day week and month agenda views and validates cleared duration fields', function () {
    operationAppointment();
    Livewire::test(Agenda::class)->set('view', 'day')->assertSee($this->customer->name)
        ->set('view', 'week')->assertSee($this->customer->name)->set('view', 'month')->assertSee('31/12/2026');
    Livewire::test(AppointmentForm::class)->set('customer_id', (string) $this->customer->id)
        ->set('address_id', (string) $this->address->id)->set('items.0.procedure_id', (string) $this->procedure->id)
        ->set('preparation_minutes', '')->call('save')->assertHasErrors('preparation_minutes');
});

it('does not introduce an automatic expiration policy for packages', function () {
    $contract = operationContract(['ends_on' => '2026-12-02']);
    expect($contract->ends_on)->toBeNull();
    operationAppointment($contract->items()->sole(), '2026-12-03T09:00');
    expect(Appointment::count())->toBe(1);
});
