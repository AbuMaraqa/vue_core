<?php

namespace App\Modules\Accounting\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * Exact decimal arithmetic for money. Floats are never used: `0.1 + 0.2` is
 * exactly `0.3` here. Amounts are stored with 4 decimals; documents round
 * their totals to the decimals of their currency.
 */
final class Decimal
{
    /**
     * The decimals amounts are stored with.
     */
    public const int SCALE = 4;

    public static function of(BigNumber|int|string|null $value): BigDecimal
    {
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        return BigDecimal::of($value);
    }

    /**
     * Round half up, e.g. `round('2.34567', 2)` is `'2.35'`.
     */
    public static function round(BigNumber|int|string|null $value, int $scale = self::SCALE): string
    {
        return (string) self::of($value)->toScale(max(0, $scale), RoundingMode::HalfUp);
    }

    /**
     * @param  iterable<BigNumber|int|string|null>  $values
     */
    public static function sum(iterable $values): BigDecimal
    {
        $total = BigDecimal::zero();

        foreach ($values as $value) {
            $total = $total->plus(self::of($value));
        }

        return $total;
    }

    /**
     * `amount × rate`, rounded.
     */
    public static function multiply(BigNumber|int|string|null $amount, BigNumber|int|string|null $rate, int $scale = self::SCALE): string
    {
        return self::round(self::of($amount)->multipliedBy(self::of($rate)), $scale);
    }

    /**
     * `amount × percent / 100`, rounded.
     */
    public static function percentOf(BigNumber|int|string|null $amount, BigNumber|int|string|null $percent, int $scale = self::SCALE): string
    {
        return self::round(self::of($amount)->multipliedBy(self::of($percent))->dividedBy(100, max(0, $scale) + 6, RoundingMode::HalfUp), $scale);
    }

    /**
     * `amount / divisor`, rounded.
     */
    public static function divide(BigNumber|int|string|null $amount, BigNumber|int|string $divisor, int $scale = self::SCALE): string
    {
        return (string) self::of($amount)->dividedBy(self::of($divisor), max(0, $scale), RoundingMode::HalfUp);
    }

    public static function isZero(BigNumber|int|string|null $value): bool
    {
        return self::of($value)->isZero();
    }

    public static function isPositive(BigNumber|int|string|null $value): bool
    {
        return self::of($value)->isPositive();
    }

    public static function isNegative(BigNumber|int|string|null $value): bool
    {
        return self::of($value)->isNegative();
    }

    public static function equals(BigNumber|int|string|null $first, BigNumber|int|string|null $second): bool
    {
        return self::of($first)->isEqualTo(self::of($second));
    }
}
