<?php

use App\Livewire\Catalog\ProcedureForm;
use App\Livewire\Catalog\ProcedureList;
use App\Livewire\Customer\AddressForm;
use App\Livewire\Customer\CustomerDetail;
use App\Livewire\Customer\CustomerForm;
use App\Livewire\Customer\CustomerList;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerProcedurePrice;
use App\Models\Procedure;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalog\SuggestProcedurePrice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->withoutVite();
    app()->setLocale('pt_BR');
    $this->owner = User::factory()->create();
    $this->tenant = Tenant::factory()->create();
    $this->owner->tenants()->attach($this->tenant, ['role' => 'owner']);
    session()->forget('current_tenant_id');
    $this->actingAs($this->owner);
});

function addressData(array $overrides = []): array
{
    return array_replace([
        'postal_code' => '01000-000', 'street' => 'Rua de Teste', 'number' => '10',
        'complement' => '', 'neighborhood' => 'Centro', 'city' => 'Cidade de Teste',
        'state' => 'SP', 'reference' => '', 'is_primary' => true,
    ], $overrides);
}

function otherBusinessRecords(): array
{
    $previous = Auth::user();
    $previousTenant = session('current_tenant_id');
    $other = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $other->tenants()->attach($tenant, ['role' => 'owner']);
    Auth::login($other);
    session()->forget('current_tenant_id');
    $customer = Customer::factory()->create(['name' => 'Cliente de outro negócio']);
    $procedure = Procedure::factory()->create(['name' => 'Procedimento de outro negócio']);
    $address = $customer->addresses()->create(addressData());
    $price = $customer->procedurePrices()->create(['procedure_id' => $procedure->id, 'price' => '85.00']);
    Auth::login($previous);
    session()->forget('current_tenant_id');
    if ($previousTenant !== null) {
        session(['current_tenant_id' => $previousTenant]);
    }

    return compact('customer', 'procedure', 'address', 'price', 'tenant');
}

it('renders every customer and catalog screen with persisted data', function () {
    $customer = Customer::factory()->create();
    $procedure = Procedure::factory()->create();
    $address = $customer->addresses()->create(addressData());
    $customer->procedurePrices()->create(['procedure_id' => $procedure->id, 'price' => '99.90']);
    foreach (['/clientes', '/clientes/nova', "/clientes/{$customer->id}", "/clientes/{$customer->id}/editar", "/clientes/{$customer->id}/enderecos/novo", "/clientes/{$customer->id}/enderecos/{$address->id}/editar", '/procedimentos', '/procedimentos/novo', "/procedimentos/{$procedure->id}/editar"] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get('/clientes/'.$customer->id)->assertSee('R$ 99,90')->assertSee('Google Maps');
});

it('creates and edits a customer with server validation', function () {
    Livewire::test(CustomerForm::class)->set('name', 'Cliente Fictícia')->set('phone', '(11) 99999-1234')
        ->set('email', 'CLIENTE@example.test')->set('notes', 'Portaria no local')
        ->call('save')->assertHasNoErrors();
    $customer = Customer::sole();
    expect($customer->tenant_id)->toBe($this->tenant->id)->and($customer->email)->toBe('cliente@example.test');
    Livewire::test(CustomerForm::class, ['customerId' => $customer->id])
        ->set('name', 'Nome atualizado')->set('email', '')->call('save')->assertHasNoErrors();
    expect($customer->fresh()->name)->toBe('Nome atualizado')->and($customer->fresh()->email)->toBeNull();
    Livewire::test(CustomerForm::class)->call('save')->assertHasErrors(['name', 'phone']);
});

it('deactivates and reactivates a customer without deleting their addresses or prices', function () {
    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create(addressData());
    $component = Livewire::test(CustomerDetail::class, ['customerId' => $customer->id]);
    $component->call('toggleActive')->assertHasNoErrors();
    expect($customer->fresh()->is_active)->toBeFalse();
    expect($customer->addresses()->count())->toBe(1);
    $component->call('toggleActive');
    expect($customer->fresh()->is_active)->toBeTrue();
});

it('searches only inside the current business and filters inactive records', function () {
    $other = otherBusinessRecords();
    Customer::factory()->create(['name' => 'Cliente visível', 'phone' => '11999991234']);
    Customer::factory()->create(['name' => 'Cliente inativa', 'is_active' => false]);
    Livewire::test(CustomerList::class)->assertSee('Cliente visível')->assertDontSee('Cliente inativa')
        ->assertDontSee('Cliente de outro negócio')->set('status', 'inactive')->assertSee('Cliente inativa')
        ->set('status', 'all')->set('search', '11999991234')->assertSee('Cliente visível')->assertDontSee('Cliente inativa');
    Livewire::test(CustomerList::class)->set('search', '%')->assertDontSee($other['customer']->name);
});

it('creates multiple addresses and changes the primary address transactionally', function () {
    $customer = Customer::factory()->create();
    Livewire::test(AddressForm::class, ['customerId' => $customer->id])
        ->set(addressData())->call('save')->assertHasNoErrors();
    $first = $customer->addresses()->sole();
    Livewire::test(AddressForm::class, ['customerId' => $customer->id])
        ->set(addressData(['street' => 'Segunda rua']))->call('save')->assertHasNoErrors();
    expect($customer->addresses()->count())->toBe(2);
    expect($customer->addresses()->where('is_primary', true)->count())->toBe(1);
    expect($first->fresh()->is_primary)->toBeFalse();
    Livewire::test(AddressForm::class, ['customerId' => $customer->id, 'addressId' => $first->id])
        ->set('street', 'Rua atualizada')->set('is_primary', true)->call('save')->assertHasNoErrors();
    expect($first->fresh()->street)->toBe('Rua atualizada')->and($first->fresh()->is_primary)->toBeTrue();
    expect($customer->addresses()->where('is_primary', true)->count())->toBe(1);
});

it('validates address fields and does not change the primary address on failure', function () {
    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create(addressData());
    Livewire::test(AddressForm::class, ['customerId' => $customer->id])->set(addressData(['state' => 'XX', 'postal_code' => 'bad']))
        ->call('save')->assertHasErrors(['state', 'postal_code']);
    expect($address->fresh()->is_primary)->toBeTrue()->and($customer->addresses()->count())->toBe(1);
});

it('soft deletes an address while preserving its original data', function () {
    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create(addressData());
    Livewire::test(CustomerDetail::class, ['customerId' => $customer->id])->call('removeAddress', $address->id)->assertHasNoErrors();
    expect($customer->addresses()->count())->toBe(0);
    expect(CustomerAddress::withTrashed()->findOrFail($address->id)->street)->toBe('Rua de Teste');
    $this->assertSoftDeleted('customer_addresses', ['id' => $address->id]);
});

it('does not allow an address of a different customer even in the same tenant', function () {
    $first = Customer::factory()->create();
    $second = Customer::factory()->create();
    $address = $second->addresses()->create(addressData());
    $this->get("/clientes/{$first->id}/enderecos/{$address->id}/editar")->assertNotFound();
    Livewire::test(CustomerDetail::class, ['customerId' => $first->id])->call('removeAddress', $address->id)->assertNotFound();
    expect($address->fresh()->deleted_at)->toBeNull();
});

it('creates edits and deactivates a procedure preserving exact decimal values', function () {
    Livewire::test(ProcedureForm::class)->set('name', 'Drenagem de teste')->set('duration_minutes', '60')->set('default_price', '125,90')
        ->call('save')->assertHasNoErrors();
    $procedure = Procedure::sole();
    expect($procedure->default_price)->toBe('125.90')->and($procedure->tenant_id)->toBe($this->tenant->id);
    Livewire::test(ProcedureForm::class, ['procedureId' => $procedure->id])->set('default_price', '99.99')->call('save')->assertHasNoErrors();
    expect($procedure->fresh()->default_price)->toBe('99.99');
    Livewire::test(ProcedureList::class)->call('toggleActive', $procedure->id);
    expect($procedure->fresh()->is_active)->toBeFalse()->and(Procedure::count())->toBe(1);
    Livewire::test(ProcedureList::class)->assertDontSee('Drenagem de teste')->set('status', 'inactive')->assertSee('Drenagem de teste');
});

it('rejects negative ambiguous and overprecision procedure prices', function (string $price) {
    Livewire::test(ProcedureForm::class)->set('name', 'Teste')->set('duration_minutes', '60')->set('default_price', $price)
        ->call('save')->assertHasErrors('default_price');
    expect(Procedure::count())->toBe(0);
})->with(['-10', '1e2', '1.200,00', '10,999']);

it('requires a positive integer duration', function () {
    Livewire::test(ProcedureForm::class)->set('name', 'Teste')->set('duration_minutes', '0')->set('default_price', '10')
        ->call('save')->assertHasErrors('duration_minutes');
});

it('saves updates and removes a unique custom price without changing the catalog', function () {
    $customer = Customer::factory()->create();
    $procedure = Procedure::factory()->create(['default_price' => '120.00']);
    $form = Livewire::test(CustomerDetail::class, ['customerId' => $customer->id]);
    $form->set('procedureId', (string) $procedure->id)->set('price', '95,50')->call('savePrice')->assertHasNoErrors();
    expect(app(SuggestProcedurePrice::class)->handle($customer->id, $procedure->id))->toBe('95.50');
    $form->set('procedureId', (string) $procedure->id)->set('price', '0')->call('savePrice')->assertHasNoErrors();
    expect(CustomerProcedurePrice::count())->toBe(1)->and($procedure->fresh()->default_price)->toBe('120.00');
    expect(app(SuggestProcedurePrice::class)->handle($customer->id, $procedure->id))->toBe('0.00');
    $form->call('removePrice', CustomerProcedurePrice::sole()->id)->assertHasNoErrors();
    expect(app(SuggestProcedurePrice::class)->handle($customer->id, $procedure->id))->toBe('120.00');
});

it('keeps a custom price independent from later catalog updates', function () {
    $customer = Customer::factory()->create();
    $procedure = Procedure::factory()->create(['default_price' => '120.00']);
    $customer->procedurePrices()->create(['procedure_id' => $procedure->id, 'price' => '95.00']);
    $procedure->update(['default_price' => '150.00']);
    expect(app(SuggestProcedurePrice::class)->handle($customer->id, $procedure->id))->toBe('95.00');
});

it('rejects a cross tenant procedure in custom pricing', function () {
    $other = otherBusinessRecords();
    $customer = Customer::factory()->create();
    Livewire::test(CustomerDetail::class, ['customerId' => $customer->id])
        ->set('procedureId', (string) $other['procedure']->id)->set('price', '10')
        ->call('savePrice')->assertHasErrors('procedureId');
    expect($customer->procedurePrices()->count())->toBe(0);
});

it('blocks cross tenant read edit deactivate and delete attempts', function () {
    $other = otherBusinessRecords();
    $ownCustomer = Customer::factory()->create();
    foreach (["/clientes/{$other['customer']->id}", "/clientes/{$other['customer']->id}/editar", "/clientes/{$other['customer']->id}/enderecos/novo", "/clientes/{$ownCustomer->id}/enderecos/{$other['address']->id}/editar", "/procedimentos/{$other['procedure']->id}/editar"] as $url) {
        $this->get($url)->assertNotFound();
    }
    Livewire::test(ProcedureList::class)->call('toggleActive', $other['procedure']->id)->assertNotFound();
    Livewire::test(CustomerDetail::class, ['customerId' => $ownCustomer->id])->call('removePrice', $other['price']->id)->assertNotFound();
    Livewire::test(CustomerDetail::class, ['customerId' => $ownCustomer->id])->call('removeAddress', $other['address']->id)->assertNotFound();
    expect(Customer::count())->toBe(1)->and(Procedure::count())->toBe(0);
    expect(DB::table('procedures')->where('id', $other['procedure']->id)->value('is_active'))->toBeTruthy();
});

it('locks record identifiers against Livewire payload changes', function () {
    $customer = Customer::factory()->create();
    expect(fn () => Livewire::test(CustomerForm::class, ['customerId' => $customer->id])->set('customerId', 99999))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('blocks tenant reassignment and writing a foreign model even without its scope', function () {
    $other = otherBusinessRecords();
    $customer = Customer::factory()->create();
    $customer->tenant_id = $other['tenant']->id;
    expect(fn () => $customer->save())->toThrow(AuthorizationException::class);
    $foreign = Customer::withoutGlobalScopes()->findOrFail($other['customer']->id);
    expect(fn () => $foreign->update(['name' => 'Tampered']))->toThrow(AuthorizationException::class);
    expect(fn () => $foreign->delete())->toThrow(AuthorizationException::class);
});

it('fails closed for business model reads without authentication', function () {
    Auth::logout();
    expect(fn () => Customer::count())->toThrow(HttpException::class);
});

it('rechecks authorization after a membership is revoked', function () {
    $customer = Customer::factory()->create();
    $form = Livewire::test(CustomerForm::class, ['customerId' => $customer->id]);
    $this->owner->tenants()->detach();
    $form->call('save')->assertForbidden();
    expect(DB::table('customers')->where('id', $customer->id)->value('name'))->toBe($customer->name);
});

it('denies catalog access to a member without owner permission', function () {
    $member = User::factory()->create();
    $member->tenants()->attach($this->tenant, ['role' => 'member']);
    $this->actingAs($member);
    $this->get('/clientes')->assertForbidden();
    $this->get('/procedimentos')->assertForbidden();
    Livewire::test(CustomerForm::class)->assertForbidden();
});

it('enforces composite foreign keys even for direct database inserts', function () {
    $other = otherBusinessRecords();
    $ownCustomer = Customer::factory()->create();
    expect(fn () => DB::table('customer_procedure_prices')->insert([
        'tenant_id' => $this->tenant->id, 'customer_id' => $ownCustomer->id,
        'procedure_id' => $other['procedure']->id, 'price' => '10.00',
    ]))->toThrow(QueryException::class);
});

it('enforces address customer tenant integrity in the database', function () {
    $other = otherBusinessRecords();
    expect(fn () => DB::table('customer_addresses')->insert(array_merge(addressData(), [
        'tenant_id' => $this->tenant->id, 'customer_id' => $other['customer']->id,
    ])))->toThrow(QueryException::class);
});

it('prevents duplicate personalized prices in the database', function () {
    $customer = Customer::factory()->create();
    $procedure = Procedure::factory()->create();
    $attributes = ['procedure_id' => $procedure->id, 'price' => '10.00'];
    $customer->procedurePrices()->create($attributes);
    expect(fn () => $customer->procedurePrices()->create($attributes))->toThrow(QueryException::class);
});

it('derives tenant identity from the backend even when attributes contain another tenant', function () {
    $other = otherBusinessRecords();
    $customer = Customer::create(['name' => 'Teste', 'phone' => '11999990000', 'tenant_id' => $other['tenant']->id]);
    expect($customer->tenant_id)->toBe($this->tenant->id);
});

it('rejects a foreign customer or procedure in the suggestion service', function () {
    $other = otherBusinessRecords();
    $customer = Customer::factory()->create();
    $procedure = Procedure::factory()->create();
    expect(fn () => app(SuggestProcedurePrice::class)->handle($other['customer']->id, $procedure->id))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => app(SuggestProcedurePrice::class)->handle($customer->id, $other['procedure']->id))
        ->toThrow(ModelNotFoundException::class);
});

it('paginates lists and resets the page when searching', function () {
    Customer::factory()->count(16)->create();
    $component = Livewire::test(CustomerList::class)->assertSee('Próxima')
        ->call('setPage', 2)->assertSet('paginators.page', 2)
        ->set('search', 'not-found-unique')->assertSet('paginators.page', 1)
        ->assertSee('Nenhuma cliente encontrada');
});

it('rejects a phone containing only punctuation', function () {
    Livewire::test(CustomerForm::class)->set('name', 'Teste')->set('phone', '----------')->call('save')->assertHasErrors('phone');
    expect(Customer::count())->toBe(0);
});
