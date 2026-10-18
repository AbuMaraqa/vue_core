<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Settings\AccountingSettings;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Carbon;

/**
 * The base currency and the exchange rates of the others.
 *
 * Scoped to the request (Octane flushes it between requests), so the base
 * currency is read once per request instead of once per journal line or bank
 * account. The remembered currency is dropped when the base currency setting
 * changes or any currency is saved or deleted ({@see Currency::booted()}).
 */
#[Scoped]
class Currencies
{
    protected ?Currency $base = null;

    public function __construct(protected AccountingSettings $settings) {}

    public function base(): Currency
    {
        if ($this->base !== null && $this->base->code === $this->settings->base_currency) {
            return $this->base;
        }

        $currency = Currency::query()->where('code', $this->settings->base_currency)->first();

        if ($currency === null) {
            throw new AccountingException(__('The base currency :code does not exist. Add it in the currencies first.', ['code' => $this->settings->base_currency]));
        }

        return $this->base = $currency;
    }

    /**
     * Forget the remembered base currency, so the next call reads it again.
     */
    public function forgetBase(): void
    {
        $this->base = null;
    }

    public function isBase(Currency|int $currency): bool
    {
        $id = $currency instanceof Currency ? $currency->id : $currency;

        return $id === $this->base()->id;
    }

    /**
     * How much one unit of the currency is worth in the base currency on the
     * date: the latest rate on or before it. Comparing with the next day keeps
     * the (currency_id, effective_date) index usable.
     */
    public function rate(Currency|int $currency, CarbonInterface|string $date): string
    {
        $currency = $currency instanceof Currency ? $currency : Currency::query()->findOrFail($currency);

        if ($this->isBase($currency)) {
            return '1.00000000';
        }

        $rate = ExchangeRate::query()
            ->where('currency_id', $currency->id)
            ->where('effective_date', '<', Carbon::parse($date)->addDay()->toDateString())
            ->latest('effective_date')
            ->value('rate');

        if ($rate === null) {
            throw new AccountingException(__('There is no exchange rate for :currency on :date.', [
                'currency' => $currency->code,
                'date' => Carbon::parse($date)->toDateString(),
            ]));
        }

        return (string) $rate;
    }

    /**
     * Convert an amount to the base currency with the given rate, rounded to
     * the decimals of the base currency.
     */
    public function toBase(string $amount, string $rate): string
    {
        return Decimal::round(Decimal::of($amount)->multipliedBy(Decimal::of($rate)), $this->base()->decimal_places);
    }
}
