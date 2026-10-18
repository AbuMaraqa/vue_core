<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Models\Currency;
use Brick\Math\BigNumber;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Re-expresses the books in a new base currency: one unit of the old base
 * currency is worth `$rate` units of the new one. Every amount kept in the
 * base currency is multiplied by the rate; documents keep their own amounts
 * and only their exchange rate to the base currency changes.
 */
final readonly class BaseCurrencyConversion
{
    public function __construct(
        public Currency $from,
        public Currency $to,
        public string $rate,
    ) {}

    /**
     * An amount of the old base currency in the new one.
     */
    public function amount(BigNumber|int|string|null $amount, int $scale = Decimal::SCALE): ?string
    {
        return $amount === null ? null : Decimal::multiply($amount, $this->rate, $scale);
    }

    /**
     * The rate to the new base currency of a document in the given currency
     * that had `$rate` to the old one. Documents in the new base currency get
     * exactly 1, so their base amounts equal their own amounts.
     */
    public function exchangeRate(int $currencyId, BigNumber|int|string $rate): string
    {
        return $currencyId === $this->to->id ? Decimal::round(1, 8) : Decimal::multiply($rate, $this->rate, 8);
    }

    /**
     * Convert base-currency columns of every row of a table.
     *
     * @param  array<string, int>  $columns  The scale of each column, e.g. `['value' => 4, 'unit_cost' => 6]`.
     * @param  (Closure(Builder): mixed)|null  $scope  Limits the rows converted.
     */
    public function convertColumns(string $table, array $columns, ?Closure $scope = null): void
    {
        DB::table($table)
            ->select(['id', ...array_keys($columns)])
            ->when($scope, fn (Builder $query) => $scope($query))
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $values = [];

                    foreach ($columns as $column => $scale) {
                        $values[$column] = $this->amount($row->{$column} === null ? null : (string) $row->{$column}, $scale);
                    }

                    DB::table($table)->where('id', $row->id)->update($values);
                }
            });
    }

    /**
     * Convert the `exchange_rate` of a document table that has `currency_id`.
     */
    public function convertExchangeRates(string $table): void
    {
        DB::table($table)
            ->select(['id', 'currency_id', 'exchange_rate'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([
                        'exchange_rate' => $this->exchangeRate((int) $row->currency_id, (string) $row->exchange_rate),
                    ]);
                }
            });
    }
}
