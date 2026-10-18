<?php

use App\Modules\Accounting\Actions\Currencies\ChangeBaseCurrency;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\Currencies;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();
});

test('the base currency is read from the database once per request', function () {
    app(Currencies::class)->base();

    DB::enableQueryLog();

    $base = app(Currencies::class)->base();
    app(Currencies::class)->isBase($base);

    expect($base->code)->toBe('JOD')
        ->and(DB::getQueryLog())->toBeEmpty();
});

test('saving or deleting a currency makes the base currency be read again', function () {
    $currencies = app(Currencies::class);
    $currencies->base();

    Currency::query()->where('code', 'JOD')->sole()->update(['decimal_places' => 2]);

    expect($currencies->base()->decimal_places)->toBe(2);

    app(AccountingSettings::class)->base_currency = 'XYZ';
    Currency::factory()->create(['code' => 'XYZ', 'decimal_places' => 0]);

    expect($currencies->base()->code)->toBe('XYZ');

    Currency::query()->where('code', 'XYZ')->sole()->delete();

    expect(fn () => $currencies->base())->toThrow(AccountingException::class, 'does not exist');
});

test('changing the base currency is seen at once', function () {
    $currencies = app(Currencies::class);
    $usd = Currency::query()->where('code', 'USD')->sole();

    expect($currencies->base()->code)->toBe('JOD');

    app(ChangeBaseCurrency::class)->handle($usd, '1.41');

    expect($currencies->base()->code)->toBe('USD')
        ->and($currencies->isBase($usd))->toBeTrue();
});

test('a rate counts from its own effective date', function () {
    $usd = Currency::query()->where('code', 'USD')->sole();
    ExchangeRate::query()->create(['currency_id' => $usd->id, 'effective_date' => '2026-06-01', 'rate' => '0.7']);
    ExchangeRate::query()->create(['currency_id' => $usd->id, 'effective_date' => '2026-06-10', 'rate' => '0.71']);

    expect(app(Currencies::class)->rate($usd, '2026-06-09'))->toBe('0.70000000')
        ->and(app(Currencies::class)->rate($usd, '2026-06-10'))->toBe('0.71000000');
});
