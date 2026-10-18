<?php

namespace App\Modules\Inventory\Printing;

use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentLine;

/**
 * The printed stock adjustment: the items counted in or written off in one
 * warehouse, with the quantities in and out and the value they moved, in the
 * base currency.
 */
class StockAdjustmentDocument extends FinancialDocument
{
    protected Currency $currency;

    public function __construct(protected StockAdjustment $adjustment)
    {
        $adjustment->loadMissing(['warehouse', 'account', 'creator', 'lines.item.unit']);

        $this->currency = app(Currencies::class)->base();
    }

    public function title(): string
    {
        return __('Stock adjustment');
    }

    public function number(): ?string
    {
        return $this->adjustment->number;
    }

    public function status(): ?array
    {
        return $this->documentStatus($this->adjustment->status);
    }

    public function details(): array
    {
        return [
            __('Date') => self::date($this->adjustment->date),
            __('Warehouse') => $this->adjustment->warehouse->label(),
            __('Adjustment account') => $this->adjustment->account->label(),
            __('Currency') => $this->currency->code,
            __('Created by') => $this->adjustment->creator?->name,
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Store keeper'), __('Approved by')];
    }

    protected function view(): string
    {
        return 'inventory::print.stock-adjustment';
    }

    protected function data(): array
    {
        $lines = $this->adjustment->lines;
        $quantities = $lines->pluck('quantity');

        return [
            'adjustment' => $this->adjustment,
            'currency' => $this->currency,
            'totals' => [
                'in' => Decimal::round(Decimal::sum($quantities->filter(fn (string $quantity): bool => Decimal::isPositive($quantity)))),
                'out' => Decimal::round(Decimal::sum($quantities->filter(fn (string $quantity): bool => Decimal::isNegative($quantity)))->abs()),
                'value' => Decimal::round(Decimal::sum($lines->map(fn (StockAdjustmentLine $line): string => $line->value))),
            ],
        ];
    }
}
