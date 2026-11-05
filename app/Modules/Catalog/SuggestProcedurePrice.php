<?php

namespace App\Modules\Catalog;

use App\Models\Customer;
use App\Models\CustomerProcedurePrice;
use App\Models\Procedure;
use Illuminate\Support\Facades\Gate;

class SuggestProcedurePrice
{
    public function handle(int $customerId, int $procedureId): string
    {
        $customer = Customer::findOrFail($customerId);
        $procedure = Procedure::findOrFail($procedureId);
        Gate::authorize('view', $customer);
        Gate::authorize('view', $procedure);

        return CustomerProcedurePrice::where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)->where('procedure_id', $procedure->id)
            ->first()?->price ?? $procedure->default_price;
    }
}
