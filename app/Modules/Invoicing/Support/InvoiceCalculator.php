<?php

namespace App\Modules\Invoicing\Support;

use App\Modules\Accounting\Support\Decimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * The amounts of an invoice line, rounded to the currency's decimals:
 *
 *   gross    = quantity × unit price
 *   discount = gross × discount %
 *   prices without tax: subtotal = gross − discount, tax = subtotal × rate
 *   prices with tax:    total = gross − discount, subtotal = total ÷ (1 + rate)
 *
 * Tax is worked out per line, so the invoice total is always the sum of its
 * lines, as printed.
 */
final class InvoiceCalculator
{
    /**
     * @return array{discount_amount: string, subtotal: string, tax_amount: string, total: string}
     */
    public static function line(
        BigNumber|string $quantity,
        BigNumber|string $unitPrice,
        BigNumber|string|null $discountPercent,
        BigNumber|string|null $taxRate,
        bool $pricesIncludeTax,
        int $decimals,
    ): array {
        $gross = Decimal::of($quantity)->multipliedBy($unitPrice);
        $discount = Decimal::of(Decimal::percentOf($gross, $discountPercent ?? '0', $decimals));
        $amount = Decimal::of(Decimal::round($gross, $decimals))->minus($discount);
        $rate = Decimal::of($taxRate ?? '0');

        if ($pricesIncludeTax) {
            $subtotal = $amount->multipliedBy(100)->dividedBy($rate->plus(100), max(0, $decimals), RoundingMode::HalfUp);
            $tax = $amount->minus($subtotal);
        } else {
            $subtotal = $amount;
            $tax = Decimal::of(Decimal::percentOf($subtotal, $rate, $decimals));
        }

        return [
            'discount_amount' => Decimal::round($discount),
            'subtotal' => Decimal::round($subtotal),
            'tax_amount' => Decimal::round($tax),
            'total' => Decimal::round($subtotal->plus($tax)),
        ];
    }

    /**
     * The totals of computed lines.
     *
     * @param  iterable<array{discount_amount: string, subtotal: string, tax_amount: string, total: string}>  $lines
     * @return array{discount_total: string, subtotal: string, tax_total: string, total: string}
     */
    public static function totals(iterable $lines): array
    {
        $lines = collect($lines);

        return [
            'discount_total' => Decimal::round(Decimal::sum($lines->pluck('discount_amount'))),
            'subtotal' => Decimal::round(Decimal::sum($lines->pluck('subtotal'))),
            'tax_total' => Decimal::round(Decimal::sum($lines->pluck('tax_amount'))),
            'total' => Decimal::round(Decimal::sum($lines->pluck('total'))),
        ];
    }
}
