<?php

namespace App\Modules\Inventory\Printing;

use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;

/**
 * The printed card of an item: its details, the stock on hand in each
 * warehouse at average cost, and its latest stock movements, as on the
 * item page.
 */
class ItemCardDocument extends FinancialDocument
{
    /**
     * How many of the latest movements are printed, as many as the item page lists.
     */
    public const int RECENT_MOVEMENTS = 10;

    protected Currency $currency;

    public function __construct(protected Item $item)
    {
        $item->loadMissing(['category', 'unit']);

        $this->currency = app(Currencies::class)->base();
    }

    public function title(): string
    {
        return __('Stock card');
    }

    public function number(): ?string
    {
        return $this->item->code;
    }

    public function status(): ?array
    {
        return $this->item->is_active ? null : ['label' => __('Inactive'), 'tone' => 'neutral'];
    }

    public function details(): array
    {
        return [
            __('Item') => $this->item->translate('name'),
            __('Type') => $this->item->type->label(),
            __('Category') => $this->item->category?->translate('name'),
            __('Unit') => $this->item->unit?->translate('name'),
            __('Barcode') => $this->item->barcode,
            __('Reorder level') => $this->item->isStock() && $this->item->reorder_level !== null ? self::quantity($this->item->reorder_level) : null,
            __('Sale price') => self::money($this->item->sale_price, $this->currency),
            __('Purchase price') => self::money($this->item->purchase_price, $this->currency),
            __('Currency') => $this->currency->code,
            __('Date') => self::date(now()),
        ];
    }

    protected function view(): string
    {
        return 'inventory::print.item-card';
    }

    protected function data(): array
    {
        /** @var Collection<int, StockLevel> $levels */
        $levels = $this->item->stockLevels()->with('warehouse')->get();

        /** @var Collection<int, StockMovement> $movements */
        $movements = $this->item->movements()->with(['warehouse', 'source'])
            ->latest('date')->latest('id')
            ->limit(self::RECENT_MOVEMENTS)
            ->get();

        return [
            'item' => $this->item,
            'currency' => $this->currency,
            'levels' => $levels,
            'movements' => $movements,
            'totals' => [
                'quantity' => Decimal::round(Decimal::sum($levels->pluck('quantity'))),
                'value' => Decimal::round(Decimal::sum($levels->pluck('value'))),
            ],
        ];
    }
}
