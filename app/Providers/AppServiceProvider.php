<?php

namespace App\Providers;

use App\Http\Middleware\ResolveTenant;
use App\Models\Appointment;
use App\Models\ContractItem;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerProcedurePrice;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PlanPeriod;
use App\Models\PlanPeriodItem;
use App\Models\Procedure;
use App\Models\ProcedurePackage;
use App\Models\ProcedurePackageItem;
use App\Models\Receivable;
use App\Models\ServiceContract;
use App\Modules\Platform\CurrentTenant;
use App\Policies\TenantRecordPolicy;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Customer::class, Procedure::class, CustomerAddress::class, CustomerProcedurePrice::class,
            ServiceContract::class, ContractItem::class, PlanPeriod::class,
            PlanPeriodItem::class, Appointment::class, Receivable::class,
            Installment::class, Payment::class, ProcedurePackage::class, ProcedurePackageItem::class] as $model) {
            Gate::policy($model, TenantRecordPolicy::class);
        }
        Livewire::addPersistentMiddleware([
            ResolveTenant::class,
            RedirectIfAuthenticated::class,
        ]);
    }
}
