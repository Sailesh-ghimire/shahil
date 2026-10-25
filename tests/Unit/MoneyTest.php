<?php

use App\Support\Money;

it('stores amounts as integer minor units', function () {
    expect(Money::toMinor('1,500.50'))->toBe(150_050)
        ->and(Money::toMinor(8500))->toBe(850_000)
        ->and(Money::toMinor(null))->toBe(0)
        ->and(Money::fromMinor(150_050))->toBe(1500.5);
});

it('formats rupees with Indian digit grouping', function (int $minor, string $expected) {
    expect(Money::format($minor))->toBe($expected);
})->with([
    'hundreds' => [999_00, 'Rs 999'],
    'lakh' => [1_50_000_00, 'Rs 1,50,000'],
    'crore' => [12_34_56_789_00, 'Rs 12,34,56,789'],
    'negative' => [-1_50_000_00, 'Rs -1,50,000'],
]);

it('shortens board totals to lakh and crore', function () {
    expect(Money::short(12_50_000_00))->toBe('Rs 12.5 lakh')
        ->and(Money::short(2_30_00_000_00))->toBe('Rs 2.3 crore')
        ->and(Money::short(45_000_00))->toBe('Rs 45,000');
});

it('uses international grouping for other currencies', function () {
    expect(Money::format(1_500_00, 'USD'))->toBe('USD 1,500');
});
