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
        // Authorize the customer and address before sharing any address with Google.
        $customer = Customer::findOrFail($customerId);
        Gate::authorize('update', $customer);
        if ($addressId !== null) {
            $customer->addresses()->findOrFail($addressId);
        }
        $coordinates = app(GeocodeAddress::class)->handle($data);

        return DB::transaction(function () use ($customerId, $addressId, $data, $coordinates) {
            $customer = Customer::lockForUpdate()->findOrFail($customerId);
            Gate::authorize('update', $customer);
            $address = $addressId === null ? new CustomerAddress : $customer->addresses()->findOrFail($addressId);

            // Serialize changes to a customer's default address, including inserts.
            if ($data['is_primary']) {
                $customer->addresses()->where('is_primary', true)
                    ->when($address->exists, fn ($query) => $query->whereKeyNot($address->id))
                    ->update(['is_primary' => false]);
            }
            $address->fill($data);
            // Coordinates are server-owned, never accepted from a frontend payload.
            $address->forceFill($coordinates);
            $address->customer_id = $customer->id;
            $address->save();

            return $address;
        });
    }
}
