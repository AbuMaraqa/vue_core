<?php

namespace App\Modules\Accounting\Events;

use App\Modules\Accounting\Support\BaseCurrencyConversion;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The books moved to a new base currency. Accounting has converted the
 * ledger; each module converts the base-currency amounts of its own tables
 * with the conversion, inside the same transaction.
 */
class BaseCurrencyChanged
{
    use Dispatchable;

    public function __construct(public BaseCurrencyConversion $conversion) {}
}
