<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Middleware\ResolveTenant;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Billing\FinanceList;
use App\Livewire\Billing\ReceivableDetail;
use App\Livewire\Catalog\PackageForm;
use App\Livewire\Catalog\PackageList;
use App\Livewire\Catalog\ProcedureForm;
use App\Livewire\Catalog\ProcedureList;
use App\Livewire\Contract\ContractDetail;
use App\Livewire\Contract\ContractForm;
use App\Livewire\Contract\ContractList;
use App\Livewire\Customer\AddressForm;
use App\Livewire\Customer\CustomerDetail;
use App\Livewire\Customer\CustomerForm;
use App\Livewire\Customer\CustomerList;
use App\Livewire\Platform\BusinessSettings;
use App\Livewire\Platform\Dashboard;
use App\Livewire\Scheduling\Agenda;
use App\Livewire\Scheduling\AppointmentDetail;
use App\Livewire\Scheduling\AppointmentForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'));

Route::get('/catalogo/{slug}', [PublicCatalogController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('catalog.show');
Route::get('/catalogo/{slug}/contato', [PublicCatalogController::class, 'contact'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:60,1')->name('catalog.contact');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', Login::class)->name('login');
    Route::get('/cadastro', Register::class)->middleware('throttle:10,1')->name('register');
});

Route::post('/sair', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', ResolveTenant::class])->group(function () {
    Route::get('/inicio', Dashboard::class)->name('dashboard');
    Route::get('/agenda', Agenda::class)->name('agenda');
    Route::get('/agenda/novo', AppointmentForm::class)->name('appointments.create');
    Route::get('/agenda/{appointmentId}/editar', AppointmentForm::class)->whereNumber('appointmentId')->name('appointments.edit');
    Route::get('/agenda/{appointmentId}', AppointmentDetail::class)->whereNumber('appointmentId')->name('appointments.show');
    Route::get('/contratacoes', ContractList::class)->name('contracts.index');
    Route::get('/contratacoes/nova', ContractForm::class)->name('contracts.create');
    Route::get('/contratacoes/{contractId}', ContractDetail::class)->whereNumber('contractId')->name('contracts.show');
    Route::get('/financeiro', FinanceList::class)->name('billing.index');
    Route::get('/financeiro/{receivableId}', ReceivableDetail::class)->whereNumber('receivableId')->name('billing.show');
    Route::get('/configuracoes', BusinessSettings::class)->name('business.settings');
    Route::get('/minha-conta', [AccountController::class, 'show'])->name('account.show');
    Route::put('/minha-conta/senha', [AccountController::class, 'updatePassword'])->middleware('throttle:5,1,account-password:')->name('account.password.update');
    Route::get('/clientes', CustomerList::class)->name('customers.index');
    Route::get('/clientes/nova', CustomerForm::class)->name('customers.create');
    Route::get('/clientes/{customerId}/editar', CustomerForm::class)->whereNumber('customerId')->name('customers.edit');
    Route::get('/clientes/{customerId}', CustomerDetail::class)->whereNumber('customerId')->name('customers.show');
    Route::get('/clientes/{customerId}/enderecos/novo', AddressForm::class)->whereNumber('customerId')->name('customers.addresses.create');
    Route::get('/clientes/{customerId}/enderecos/{addressId}/editar', AddressForm::class)->whereNumber(['customerId', 'addressId'])->name('customers.addresses.edit');
    Route::get('/procedimentos', ProcedureList::class)->name('procedures.index');
    Route::get('/pacotes', PackageList::class)->name('packages.index');
    Route::get('/pacotes/novo', PackageForm::class)->name('packages.create');
    Route::get('/pacotes/{packageId}/editar', PackageForm::class)->whereNumber('packageId')->name('packages.edit');
    Route::get('/procedimentos/novo', ProcedureForm::class)->name('procedures.create');
    Route::get('/procedimentos/{procedureId}/editar', ProcedureForm::class)->whereNumber('procedureId')->name('procedures.edit');
});
