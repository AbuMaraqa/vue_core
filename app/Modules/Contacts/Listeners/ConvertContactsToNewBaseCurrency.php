<?php

namespace App\Modules\Contacts\Listeners;

use App\Modules\Accounting\Events\BaseCurrencyChanged;
use Illuminate\Database\Query\Builder;

/**
 * Credit limits of contacts without their own currency are base amounts.
 */
class ConvertContactsToNewBaseCurrency
{
    public function handle(BaseCurrencyChanged $event): void
    {
        $event->conversion->convertColumns('contacts', ['credit_limit' => 4], fn (Builder $query) => $query->whereNull('currency_id'));
    }
}
