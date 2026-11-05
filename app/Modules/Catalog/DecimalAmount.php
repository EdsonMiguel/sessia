<?php

namespace App\Modules\Catalog;

use InvalidArgumentException;

class DecimalAmount
{
    public static function cents(string $value): int
    {
        return (int) str_replace('.', '', self::normalize($value));
    }

    public static function fromCents(int $value): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Valor fora do limite financeiro suportado.');
        }

        return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function normalize(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^\d{1,10}(?:[.,]\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Informe um valor positivo com até duas casas decimais, sem separador de milhar.');
        }
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $value)), 2, '');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 2, '0');
    }

    public static function format(string $value): string
    {
        // Aggregated balances may exceed the limit of an individual DECIMAL(12,2).
        if (! preg_match('/^\d+(?:[.,]\d{1,2})?$/D', trim($value))) {
            throw new InvalidArgumentException('Valor monetário inválido.');
        }
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', trim($value))), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = str_pad($fraction, 2, '0');

        return 'R$ '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;
    }

    public static function rules(): array
    {
        return ['required', 'string', 'regex:/^\d{1,10}(?:[.,]\d{1,2})?$/D'];
    }
}
