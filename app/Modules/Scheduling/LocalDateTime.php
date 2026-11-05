<?php

namespace App\Modules\Scheduling;

use App\Modules\Platform\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class LocalDateTime
{
    public static function utc(string $value, string $field = 'starts_at'): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, app(CurrentTenant::class)->get()->timezone);
            if (! $date || $date->format('Y-m-d\TH:i') !== $value) {
                throw new \InvalidArgumentException;
            }

            return $date->utc();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([$field => 'Informe uma data e hora válidas no fuso do negócio.']);
        }
    }
}
