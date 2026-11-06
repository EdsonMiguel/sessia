<?php

use App\Livewire\Customer\AddressForm;
use App\Livewire\Customer\CustomerForm;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Customer\SaveCustomerAddress;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
    config(['services.google_maps.key' => 'fake-server-key']);
    $this->owner = User::factory()->create();
    $this->tenant = Tenant::factory()->create();
    $this->owner->tenants()->attach($this->tenant, ['role' => 'owner']);
    $this->actingAs($this->owner);
    session()->forget('current_tenant_id');
    $this->customer = Customer::factory()->create();
    $this->addressData = [
        'postal_code' => '01000-000', 'street' => 'Rua Fictícia', 'number' => '42',
        'neighborhood' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP',
        'complement' => 'Apartamento privado', 'reference' => 'Referência privada', 'is_primary' => true,
    ];
    $this->googleResponse = ['status' => 'OK', 'results' => [[
        'geometry' => ['location' => ['lat' => -23.5505199, 'lng' => -46.6333094]],
    ]]];
});

it('geocodes a new customer address server side and stores decimal coordinates', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response($this->googleResponse)]);
    Livewire::test(CustomerForm::class)->set('name', 'Cliente fictícia')->set('phone', '11999991234')
        ->set(collect($this->addressData)->except('is_primary')->all())->call('save')->assertHasNoErrors();
    $customer = Customer::where('name', 'Cliente fictícia')->firstOrFail();
    $address = $customer->addresses()->sole();
    expect($address->latitude)->toBe('-23.5505199')->and($address->longitude)->toBe('-46.6333094')
        ->and($address->geocoding_status)->toBe('located')->and($address->geocoded_at)->not->toBeNull()
        ->and($address->tenant_id)->toBe($this->tenant->id);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['key'] === 'fake-server-key'
        && $request['address'] === 'Rua Fictícia, 42, Centro, São Paulo, SP, 01000-000, Brasil'
        && $request['components'] === 'country:BR' && $request['language'] === 'pt-BR'
        && ! str_contains($request->url(), 'privad'));
    $this->get('/clientes/'.$customer->id)->assertOk()->assertSee('-23.5505199')->assertDontSee('fake-server-key');
});

it('queries coordinates when adding and editing secondary addresses and editing the customer', function () {
    Http::fakeSequence()->push($this->googleResponse)->push(['status' => 'OK', 'results' => [[
        'geometry' => ['location' => ['lat' => -22.1, 'lng' => -45.2]],
    ]]])->push($this->googleResponse);
    Livewire::test(AddressForm::class, ['customerId' => $this->customer->id])
        ->set($this->addressData)->call('save')->assertHasNoErrors();
    $address = $this->customer->addresses()->sole();
    Livewire::test(AddressForm::class, ['customerId' => $this->customer->id, 'addressId' => $address->id])
        ->set('number', '43')->call('save')->assertHasNoErrors();
    expect($address->fresh()->latitude)->toBe('-22.1000000')->and($address->fresh()->longitude)->toBe('-45.2000000');
    expect($address->fresh()->is_primary)->toBeTrue();
    Livewire::test(CustomerForm::class, ['customerId' => $this->customer->id])->set('number', '44')->call('save')->assertHasNoErrors();
    expect($address->fresh()->latitude)->toBe('-23.5505199')->and($address->fresh()->number)->toBe('44');
    Http::assertSentCount(3);
});

it('does not query Google when there is no address or before validation succeeds', function () {
    Http::fake();
    Livewire::test(CustomerForm::class)->set('name', 'Sem endereço')->set('phone', '11999991234')->call('save')->assertHasNoErrors();
    Livewire::test(AddressForm::class, ['customerId' => $this->customer->id])->set('street', 'Incompleto')
        ->call('save')->assertHasErrors('postal_code');
    Http::assertNothingSent();
});

it('saves without coordinates and explains that the key is missing', function () {
    config(['services.google_maps.key' => '']);
    Http::fake();
    Livewire::test(CustomerForm::class, ['customerId' => $this->customer->id])
        ->set(collect($this->addressData)->except('is_primary')->all())->call('save')->assertHasNoErrors();
    $address = $this->customer->addresses()->sole();
    expect($address->latitude)->toBeNull()->and($address->longitude)->toBeNull()->and($address->geocoding_status)->toBe('not_configured');
    $this->get('/clientes/'.$this->customer->id)->assertOk()->assertSee('integração com o Google ainda não foi configurada');
    Http::assertNothingSent();
});

it('clears outdated coordinates on failed lookups and permits retrying the same address', function () {
    Http::fakeSequence()->push($this->googleResponse)->push(['status' => 'ZERO_RESULTS', 'results' => []])->push($this->googleResponse);
    $action = app(SaveCustomerAddress::class);
    $address = $action->handle($this->customer->id, null, $this->addressData);
    $changed = array_replace($this->addressData, ['street' => 'Novo endereço fictício']);
    $address = $action->handle($this->customer->id, $address->id, $changed);
    expect($address->latitude)->toBeNull()->and($address->longitude)->toBeNull()->and($address->geocoded_at)->toBeNull()
        ->and($address->geocoding_status)->toBe('not_found');
    $address = $action->handle($this->customer->id, $address->id, $changed);
    expect($address->geocoding_status)->toBe('located')->and($address->latitude)->toBe('-23.5505199');
    Http::assertSentCount(3);
});

it('handles denied exhausted malformed ambiguous and invalid Google results without an error 500', function (array $payload, int $httpStatus, string $status) {
    Http::fake(['maps.googleapis.com/*' => Http::response($payload, $httpStatus)]);
    Livewire::test(AddressForm::class, ['customerId' => $this->customer->id])
        ->set($this->addressData)->call('save')->assertHasNoErrors();
    $address = $this->customer->addresses()->sole();
    expect($address->latitude)->toBeNull()->and($address->longitude)->toBeNull()->and($address->geocoding_status)->toBe($status);
})->with([
    'denied' => [['status' => 'REQUEST_DENIED', 'error_message' => 'secret-provider-detail'], 200, 'configuration_error'],
    'quota' => [['status' => 'OVER_QUERY_LIMIT'], 200, 'unavailable'],
    'http error' => [[], 503, 'unavailable'],
    'malformed' => [[], 200, 'unavailable'],
    'empty result' => [['status' => 'OK', 'results' => []], 200, 'not_found'],
    'ambiguous' => [['status' => 'OK', 'results' => [[], []]], 200, 'not_found'],
    'partial' => [['status' => 'OK', 'results' => [['partial_match' => true]]], 200, 'not_found'],
    'out of range' => [['status' => 'OK', 'results' => [['geometry' => ['location' => ['lat' => 91, 'lng' => 181]]]]], 200, 'unavailable'],
    'missing coordinates' => [['status' => 'OK', 'results' => [['geometry' => []]]], 200, 'unavailable'],
]);

it('handles connection failures without exposing keys or provider exception details', function () {
    Http::fake(fn () => throw new ConnectionException('https://example.test/?key=fake-server-key&private-address'));
    Livewire::test(AddressForm::class, ['customerId' => $this->customer->id])->set($this->addressData)->call('save')->assertHasNoErrors();
    expect($this->customer->addresses()->sole()->geocoding_status)->toBe('unavailable');
    $this->get('/clientes/'.$this->customer->id)->assertOk()->assertSee('Não foi possível consultar as coordenadas')
        ->assertDontSee('fake-server-key')->assertDontSee('private-address');
});

it('rejects foreign addresses and customers before making an external request', function () {
    $other = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $other->tenants()->attach($tenant, ['role' => 'owner']);
    $this->actingAs($other);
    session()->forget('current_tenant_id');
    $foreignCustomer = Customer::factory()->create();
    $foreignAddress = $foreignCustomer->addresses()->create($this->addressData);
    $this->actingAs($this->owner);
    session()->forget('current_tenant_id');
    Http::fake();
    $action = app(SaveCustomerAddress::class);
    expect(fn () => $action->handle($foreignCustomer->id, null, $this->addressData))->toThrow(ModelNotFoundException::class);
    expect(fn () => $action->handle($this->customer->id, $foreignAddress->id, $this->addressData))->toThrow(ModelNotFoundException::class);
    Http::assertNothingSent();
});
