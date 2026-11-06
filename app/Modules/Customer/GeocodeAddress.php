<?php

namespace App\Modules\Customer;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeocodeAddress
{
    public function handle(array $address): array
    {
        $key = trim((string) config('services.google_maps.key'));
        if ($key === '') {
            return $this->unavailable('not_configured');
        }

        // Do not send names, contact details, complements or private reference notes.
        $query = implode(', ', [
            $address['street'], $address['number'], $address['neighborhood'],
            $address['city'], $address['state'], $address['postal_code'], 'Brasil',
        ]);
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(8)
                ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'address' => $query, 'key' => $key, 'language' => 'pt-BR', 'components' => 'country:BR',
                ]);
        } catch (ConnectionException) {
            // The exception URL can contain both the key and a private address.
            return $this->unavailable('unavailable');
        }

        if (! $response->successful()) {
            return $this->unavailable('unavailable');
        }
        $status = $response->json('status');
        if ($status !== 'OK') {
            return $this->unavailable(match ($status) {
                'ZERO_RESULTS' => 'not_found',
                'REQUEST_DENIED' => 'configuration_error',
                default => 'unavailable',
            });
        }

        $results = $response->json('results');
        if (! is_array($results) || count($results) !== 1 || data_get($results, '0.partial_match') === true) {
            return $this->unavailable('not_found');
        }
        $latitude = data_get($results, '0.geometry.location.lat');
        $longitude = data_get($results, '0.geometry.location.lng');
        if (! is_numeric($latitude) || ! is_numeric($longitude)
            || ! is_finite((float) $latitude) || ! is_finite((float) $longitude)
            || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return $this->unavailable('unavailable');
        }

        return [
            'latitude' => number_format((float) $latitude, 7, '.', ''),
            'longitude' => number_format((float) $longitude, 7, '.', ''),
            'geocoding_status' => 'located', 'geocoded_at' => now(),
        ];
    }

    private function unavailable(string $status): array
    {
        return ['latitude' => null, 'longitude' => null, 'geocoding_status' => $status, 'geocoded_at' => null];
    }
}
