<?php

use App\Modules\Catalog\DecimalAmount;

it('normalizes decimal input without floating point arithmetic', function (string $input, string $expected) {
    expect(DecimalAmount::normalize($input))->toBe($expected);
})->with([
    ['120,50', '120.50'],
    ['0', '0.00'],
    ['0001.1', '1.10'],
    [' 99,99 ', '99.99'],
    ['9999999999.99', '9999999999.99'],
]);

it('rejects ambiguous and invalid amounts', function (string $input) {
    expect(fn () => DecimalAmount::normalize($input))->toThrow(InvalidArgumentException::class);
})->with(['-1', '1e2', '10,001', '1.200,00', '1,200.00', 'NaN', '10000000000', '', '.5']);

it('formats Brazilian currency using decimal strings', function () {
    expect(DecimalAmount::format('1234567890.09'))->toBe('R$ 1.234.567.890,09');
    expect(DecimalAmount::format('0.00'))->toBe('R$ 0,00');
});

it('formats aggregated balances beyond the limit of individual financial records', function () {
    expect(DecimalAmount::format(DecimalAmount::fromCents(1999999999998)))->toBe('R$ 19.999.999.999,98');
});
