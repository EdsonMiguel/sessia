<?php

use App\Livewire\Catalog\PackageForm;
use App\Livewire\Catalog\PackageList;
use App\Livewire\Contract\ContractForm;
use App\Models\Customer;
use App\Models\CustomerProcedurePrice;
use App\Models\Procedure;
use App\Models\ProcedurePackage;
use App\Models\ServiceContract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->owner = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['slug' => 'pacotes-teste', 'phone' => '+5511999991234']);
    $this->owner->tenants()->attach($this->tenant, ['role' => 'owner']);
    $this->actingAs($this->owner);
    session()->forget('current_tenant_id');
    $this->procedure = Procedure::factory()->create(['name' => 'Drenagem teste', 'default_price' => '120.00']);
    $this->second = Procedure::factory()->create(['name' => 'Massagem teste', 'default_price' => '150.00']);
    $this->customer = Customer::factory()->create();
    $this->package = ProcedurePackage::create(['name' => 'Pacote de cuidados', 'description' => 'Combinação de sessões', 'total' => '480.00', 'show_in_catalog' => true]);
    $this->package->items()->create(['procedure_id' => $this->procedure->id, 'quantity' => 4]);
    $this->package->items()->create(['procedure_id' => $this->second->id, 'quantity' => 2]);
    $this->otherOwner = User::factory()->create();
    $this->otherTenant = Tenant::factory()->create(['slug' => 'pacotes-outro', 'phone' => '+5521999991234']);
    $this->otherOwner->tenants()->attach($this->otherTenant, ['role' => 'owner']);
    $this->actingAs($this->otherOwner);
    session()->forget('current_tenant_id');
    $this->foreign = Procedure::factory()->create(['name' => 'Procedimento de outro negócio']);
    $this->foreignPackage = ProcedurePackage::create(['name' => 'Pacote de outro negócio', 'total' => '99.00', 'show_in_catalog' => true]);
    $this->foreignPackage->items()->create(['procedure_id' => $this->foreign->id, 'quantity' => 1]);
    $this->actingAs($this->owner);
    session()->forget('current_tenant_id');
});

it('creates and edits a reusable package with quantities and an independent decimal total', function () {
    Livewire::test(PackageForm::class)->set('name', 'Novo conjunto')->set('total', '350,75')
        ->set('show_in_catalog', true)->set('items', [
            ['procedure_id' => (string) $this->procedure->id, 'quantity' => '3'],
            ['procedure_id' => (string) $this->second->id, 'quantity' => '2'],
        ])->call('save')->assertHasNoErrors()->assertRedirect(route('packages.index'));
    $package = ProcedurePackage::where('name', 'Novo conjunto')->firstOrFail();
    expect($package->total)->toBe('350.75')->and($package->tenant_id)->toBe($this->tenant->id)
        ->and($package->items->pluck('quantity')->all())->toBe([3, 2])->and($package->show_in_catalog)->toBeTrue();
    Livewire::test(PackageForm::class, ['packageId' => $package->id])->assertSet('total', '350,75')
        ->set('name', 'Conjunto revisado')->set('show_in_catalog', false)
        ->set('items', [['procedure_id' => (string) $this->second->id, 'quantity' => '5']])
        ->call('save')->assertHasNoErrors();
    expect($package->fresh()->name)->toBe('Conjunto revisado')->and($package->fresh()->show_in_catalog)->toBeFalse()
        ->and($package->items()->count())->toBe(1)->and($package->items()->first()->quantity)->toBe(5);
    $this->get('/pacotes')->assertOk()->assertSee('Conjunto revisado')->assertDontSee('Pacote de outro negócio');
    $this->get('/pacotes/novo')->assertOk();
    $this->get('/pacotes/'.$package->id.'/editar')->assertOk();
});

it('validates empty duplicate invalid and inactive package items without partial writes', function () {
    $inactive = Procedure::factory()->create(['is_active' => false]);
    $cases = [
        [[], 'items'],
        [[['procedure_id' => $this->procedure->id, 'quantity' => '0']], 'items.0.quantity'],
        [[['procedure_id' => $this->procedure->id, 'quantity' => '65536']], 'items.0.quantity'],
        [[['procedure_id' => $this->foreign->id, 'quantity' => '1']], 'items.0.procedure_id'],
        [[['procedure_id' => $inactive->id, 'quantity' => '1']], 'items.0.procedure_id'],
        [[['procedure_id' => $this->procedure->id, 'quantity' => '1'], ['procedure_id' => $this->procedure->id, 'quantity' => '2']], 'items.0.procedure_id'],
    ];
    foreach ($cases as [$items, $error]) {
        Livewire::test(PackageForm::class, ['packageId' => $this->package->id])
            ->set('name', 'Não deve salvar')->set('items', $items)->call('save')->assertHasErrors($error);
    }
    Livewire::test(PackageForm::class)->set('name', 'Valor inválido')->set('total', '1.999')
        ->call('save')->assertHasErrors('total');
    expect($this->package->fresh()->name)->toBe('Pacote de cuidados')
        ->and($this->package->items()->count())->toBe(2)->and(ProcedurePackage::count())->toBe(1);
});

it('blocks reading editing activating and applying packages from other businesses', function () {
    Livewire::test(PackageForm::class, ['packageId' => $this->foreignPackage->id])->assertNotFound();
    Livewire::test(PackageList::class)->call('toggleActive', $this->foreignPackage->id)->assertNotFound();
    Livewire::test(ContractForm::class)->set('type', 'package')->assertDontSee('Pacote de outro negócio')
        ->set('package_id', (string) $this->foreignPackage->id)->call('applyPackage')->assertNotFound();
    $this->get('/pacotes/'.$this->foreignPackage->id.'/editar')->assertNotFound();
    expect(ProcedurePackage::withoutGlobalScope('tenant')->find($this->foreignPackage->id)->is_active)->toBeTrue();
});

it('enforces same tenant package associations in the database', function () {
    expect(fn () => DB::table('procedure_package_items')->insert([
        'tenant_id' => $this->tenant->id, 'procedure_package_id' => $this->package->id,
        'procedure_id' => $this->foreign->id, 'quantity' => 1,
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('procedure_package_items')->insert([
        'tenant_id' => $this->tenant->id, 'procedure_package_id' => $this->foreignPackage->id,
        'procedure_id' => $this->procedure->id, 'quantity' => 1,
    ]))->toThrow(QueryException::class);
});

it('uses habitual prices with the package total and allows personalization without changing the template', function () {
    CustomerProcedurePrice::create(['customer_id' => $this->customer->id, 'procedure_id' => $this->procedure->id, 'price' => '90.00']);
    Livewire::test(ContractForm::class)->set('type', 'package')->set('customer_id', (string) $this->customer->id)
        ->set('package_id', (string) $this->package->id)->call('applyPackage')->assertHasNoErrors()
        ->assertSet('total', '480,00')->assertSet('items.0.unit_price', '90.00')->assertSet('items.1.unit_price', '150.00')
        ->set('items.0.quantity', '6')->set('items.0.unit_price', '85,50')->set('total', '500,25')
        ->call('save')->assertHasNoErrors();
    $contract = ServiceContract::with('items')->firstOrFail();
    expect($contract->type)->toBe('package')->and($contract->total)->toBe('500.25')
        ->and($contract->items[0]->quantity)->toBe(6)->and($contract->items[0]->unit_price)->toBe('85.50')
        ->and($this->package->fresh()->total)->toBe('480.00')->and($this->package->items()->first()->quantity)->toBe(4);
    Livewire::test(PackageForm::class, ['packageId' => $this->package->id])->set('total', '999')
        ->set('items', [['procedure_id' => (string) $this->second->id, 'quantity' => '1']])->call('save')->assertHasNoErrors();
    $this->procedure->update(['name' => 'Novo nome', 'default_price' => '200.00']);
    expect($contract->fresh()->total)->toBe('500.25')->and($contract->items()->count())->toBe(2)
        ->and($contract->items()->first()->procedure_name)->toBe('Drenagem teste')
        ->and($contract->items()->first()->quantity)->toBe(6)->and($contract->items()->first()->unit_price)->toBe('85.50');
});

it('allows a custom contract package without saving a new reusable template', function () {
    Livewire::test(ContractForm::class)->set('type', 'package')->set('package_id', (string) $this->package->id)
        ->call('applyPackage')->assertSet('items.0.unit_price', '120.00')->call('startCustomPackage')
        ->assertSet('package_id', '')->assertSet('total', '')->assertSet('items', [['procedure_id' => '', 'quantity' => '1', 'unit_price' => '']])
        ->set('customer_id', (string) $this->customer->id)->set('total', '300')
        ->set('items', [['procedure_id' => (string) $this->procedure->id, 'quantity' => '3', 'unit_price' => '100']])
        ->call('save')->assertHasNoErrors();
    expect(ServiceContract::firstOrFail()->type)->toBe('package')->and(ProcedurePackage::count())->toBe(1);
});

it('rejects unavailable templates and still validates customized contract items at save', function () {
    $form = Livewire::test(ContractForm::class)->set('type', 'package')->set('package_id', (string) $this->package->id);
    $this->procedure->update(['is_active' => false]);
    $form->call('applyPackage')->assertHasErrors('package_id')->assertSet('total', '');
    $this->package->update(['is_active' => false]);
    Livewire::test(ContractForm::class)->set('type', 'package')->set('package_id', (string) $this->package->id)
        ->call('applyPackage')->assertNotFound();
    Livewire::test(ContractForm::class)->set('type', 'package')->set('customer_id', (string) $this->customer->id)
        ->set('total', '100')->set('items', [['procedure_id' => (string) $this->foreign->id, 'quantity' => '1', 'unit_price' => '100']])
        ->call('save')->assertNotFound();
    expect(ServiceContract::count())->toBe(0);
});

it('publishes only available opted in packages and their included procedures without authentication', function () {
    $hidden = ProcedurePackage::create(['name' => 'Pacote privado', 'total' => '100']);
    $hidden->items()->create(['procedure_id' => $this->procedure->id, 'quantity' => 1]);
    $inactive = ProcedurePackage::create(['name' => 'Pacote inativo', 'total' => '100', 'show_in_catalog' => true, 'is_active' => false]);
    $inactive->items()->create(['procedure_id' => $this->procedure->id, 'quantity' => 1]);
    ProcedurePackage::create(['name' => 'Pacote sem itens', 'total' => '100', 'show_in_catalog' => true]);
    Auth::logout();
    session()->forget('current_tenant_id');
    $this->get('/catalogo/pacotes-teste')->assertOk()->assertSee('Pacote de cuidados')
        ->assertSee('4 sessões de Drenagem teste')->assertSee('2 sessões de Massagem teste')->assertSee('R$ 480,00')
        ->assertDontSee('Pacote privado')->assertDontSee('Pacote inativo')->assertDontSee('Pacote de outro negócio')
        ->assertDontSee('Pacote sem itens')->assertDontSee('Novos cuidados em breve');
    foreach ([$hidden->id, $inactive->id, $this->foreignPackage->id, '999999', 'invalid'] as $id) {
        $this->get('/catalogo/pacotes-teste/contato?package='.$id)->assertNotFound();
    }
    $this->get('/catalogo/pacotes-teste/contato?package[]=1')->assertNotFound();
    $this->get('/catalogo/pacotes-teste/contato?package='.$this->package->id.'&procedure='.$this->procedure->id)->assertNotFound();
    $this->get('/pacotes')->assertRedirect(route('login'));
});

it('uses the slug tenant for packages even when authenticated to another business', function () {
    $this->actingAs($this->otherOwner);
    session(['current_tenant_id' => $this->otherTenant->id]);
    $this->get('/catalogo/pacotes-teste')->assertOk()->assertSee('Pacote de cuidados')->assertSee('Drenagem teste')->assertDontSee('Pacote de outro negócio');
    $response = $this->get('/catalogo/pacotes-teste/contato?package='.$this->package->id)->assertRedirect();
    $url = $response->headers->get('Location');
    expect(parse_url($url, PHP_URL_HOST))->toBe('wa.me')->and(parse_url($url, PHP_URL_PATH))->toBe('/5511999991234');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query['text'])->toContain('pacote Pacote de cuidados', $this->tenant->name)
        ->and(session('current_tenant_id'))->toBe($this->otherTenant->id);
});

it('removes packages from public contact immediately when unpublished or deactivated', function () {
    foreach (['show_in_catalog', 'is_active'] as $field) {
        $this->actingAs($this->owner);
        $this->package->update([$field => false]);
        Auth::logout();
        $this->get('/catalogo/pacotes-teste')->assertOk()->assertDontSee('Pacote de cuidados');
        $this->get('/catalogo/pacotes-teste/contato?package='.$this->package->id)->assertNotFound();
        $this->actingAs($this->owner);
        $this->package->update([$field => true]);
    }
    $this->procedure->update(['is_active' => false]);
    Auth::logout();
    $this->get('/catalogo/pacotes-teste')->assertOk()->assertDontSee('Pacote de cuidados');
    $this->get('/catalogo/pacotes-teste/contato?package='.$this->package->id)->assertNotFound();
});

it('allows deactivation and reactivation and denies a revoked owner', function () {
    Livewire::test(PackageList::class)->call('toggleActive', $this->package->id);
    expect($this->package->fresh()->is_active)->toBeFalse();
    Livewire::test(PackageList::class)->set('status', 'inactive')->assertSee('Pacote de cuidados')->call('toggleActive', $this->package->id);
    expect($this->package->fresh()->is_active)->toBeTrue();
    $form = Livewire::test(PackageForm::class, ['packageId' => $this->package->id])->set('name', 'Não autorizado');
    $this->owner->tenants()->detach($this->tenant);
    $form->call('save')->assertForbidden();
    expect(DB::table('procedure_packages')->where('id', $this->package->id)->value('name'))->toBe('Pacote de cuidados');
});

it('paginates public packages independently and escapes their content', function () {
    $this->package->update(['name' => 'AAA <script>alert(1)</script>']);
    for ($index = 0; $index < 12; $index++) {
        $package = ProcedurePackage::create(['name' => 'Pacote '.$index, 'total' => '10.00', 'show_in_catalog' => true]);
        $package->items()->create(['procedure_id' => $this->procedure->id, 'quantity' => 1]);
    }
    Auth::logout();
    $this->get('/catalogo/pacotes-teste')->assertOk()
        ->assertViewHas('packages', fn ($list) => $list->count() === 12 && $list->total() === 13)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->get('/catalogo/pacotes-teste?packages_page=2')->assertOk()
        ->assertViewHas('packages', fn ($list) => $list->count() === 1)
        ->assertViewHas('procedures', fn ($list) => $list->currentPage() === 1);
});
