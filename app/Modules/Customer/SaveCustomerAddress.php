<?php

namespace App\Modules\Customer;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveCustomerAddress
{
    public function handle(int $customerId, ?int $addressId, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customerId, $addressId, $data) {
            $customer = Customer::lockForUpdate()->findOrFail($customerId);
            Gate::authorize('update', $customer);
            $address = $addressId === null ? new CustomerAddress : $customer->addresses()->findOrFail($addressId);

            // Serialize changes to a customer's default address, including inserts.
            if ($data['is_primary']) {
                $customer->addresses()->where('is_primary', true)->update(['is_primary' => false]);
            }
            $address->fill($data);
            $address->customer_id = $customer->id;
            $address->save();

            return $address;
        });
    }
}
