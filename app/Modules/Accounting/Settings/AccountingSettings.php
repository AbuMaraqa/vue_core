<?php

namespace App\Modules\Accounting\Settings;

use Spatie\LaravelSettings\Settings;

class AccountingSettings extends Settings
{
    /**
     * The currency of the books, e.g. `JOD`. Every amount is also stored in it.
     */
    public string $base_currency;

    /**
     * Nothing dated on or before this day (Y-m-d) can be posted or changed.
     */
    public ?string $lock_date;

    /**
     * The account id of each AccountRole, keyed by the role value.
     *
     * @var array<string, int>
     */
    public array $default_accounts;

    /**
     * Number prefixes that replace DocumentType::defaultPrefix(), keyed by type.
     *
     * @var array<string, string>
     */
    public array $number_prefixes;

    public ?string $company_tax_number;

    public ?string $company_address;

    /**
     * Whether new documents start with tax-inclusive prices.
     */
    public bool $prices_include_tax;

    /**
     * Whether stock may go below zero when it is sold or issued.
     */
    public bool $allow_negative_stock;

    public static function group(): string
    {
        return 'accounting';
    }
}
