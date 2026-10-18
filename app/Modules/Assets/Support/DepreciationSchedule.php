<?php

namespace App\Modules\Assets\Support;

use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Assets\Models\Asset;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The month-by-month depreciation of an asset over its life, from its
 * depreciation start month, rounded to the base currency:
 *
 * - straight line: (cost − salvage − opening depreciation) ÷ months of life;
 * - declining balance: book value × yearly rate ÷ 12 each month, and in the
 *   last month of life whatever is left down to the salvage value.
 *
 * Nothing ever goes below the salvage value. The schedule is worked out
 * from the asset's figures alone, so a run always posts the same amounts
 * the asset page shows.
 */
class DepreciationSchedule
{
    public function __construct(protected Currencies $currencies) {}

    /**
     * The schedule, or only its months through `until` when given: each month
     * depends on the ones before it only, so the rows are the same.
     *
     * @return list<array{month: string, amount: string, accumulated: string, book_value: string}>
     */
    public function rows(Asset $asset, ?CarbonInterface $until = null): array
    {
        $lastMonth = $until?->format('Y-m');
        $decimals = $this->currencies->base()->decimal_places;
        $cost = Decimal::of($asset->cost);
        $floor = Decimal::of($asset->salvage_value);
        $accumulated = Decimal::of($asset->opening_depreciation);
        $life = max(1, $asset->useful_life_months);
        $month = Carbon::parse($asset->depreciation_start_date)->startOfMonth();
        $straightMonthly = $cost->minus($floor)->minus($accumulated)->dividedBy($life, max(0, $decimals) + 6, RoundingMode::HalfUp);
        $rate = Decimal::of($asset->declining_rate ?? $this->defaultDecliningRate($life));
        $rows = [];

        for ($index = 0; $index < $life; $index++) {
            $remaining = $cost->minus($floor)->minus($accumulated);

            if (! $remaining->isPositive() || ($lastMonth !== null && $month->format('Y-m') > $lastMonth)) {
                break;
            }

            $amount = match (true) {
                $index === $life - 1 => $remaining,
                $asset->method === DepreciationMethod::StraightLine => Decimal::of(Decimal::round($straightMonthly, $decimals)),
                default => Decimal::of(Decimal::round($cost->minus($accumulated)->multipliedBy($rate)->dividedBy(1200, max(0, $decimals) + 6, RoundingMode::HalfUp), $decimals)),
            };

            $amount = BigDecimal::min($amount, $remaining);
            $accumulated = $accumulated->plus($amount);

            $rows[] = [
                'month' => $month->format('Y-m'),
                'amount' => Decimal::round($amount),
                'accumulated' => Decimal::round($accumulated),
                'book_value' => Decimal::round($cost->minus($accumulated)),
            ];

            $month = $month->addMonth();
        }

        return $rows;
    }

    /**
     * The depreciation not yet posted up to the end of a month: the months
     * after `depreciated_until`, through `until`.
     *
     * @return array{amount: string, months: int, from: string|null, to: string|null}
     */
    public function due(Asset $asset, CarbonInterface $until): array
    {
        $after = $asset->depreciated_until?->format('Y-m');
        $through = $until->format('Y-m');

        $rows = array_values(array_filter(
            $this->rows($asset, $until),
            fn (array $row): bool => ($after === null || $row['month'] > $after) && $row['month'] <= $through,
        ));

        if ($rows === []) {
            return ['amount' => '0', 'months' => 0, 'from' => null, 'to' => null];
        }

        return [
            'amount' => Decimal::round(Decimal::sum(array_column($rows, 'amount'))),
            'months' => count($rows),
            'from' => Carbon::parse($rows[0]['month'].'-01')->toDateString(),
            'to' => Carbon::parse($rows[count($rows) - 1]['month'].'-01')->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Double the straight-line rate: 2 ÷ years of life, as a yearly percent.
     */
    public function defaultDecliningRate(int $lifeMonths): string
    {
        return Decimal::divide(2400, max(1, $lifeMonths), 4);
    }
}
