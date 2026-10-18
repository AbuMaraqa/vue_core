<?php

namespace App\Modules\Inventory\Exports;

use App\Modules\Foundation\Exports\Exporter;
use App\Modules\Inventory\Models\Item;

/**
 * @extends Exporter<Item>
 */
class ItemsExporter extends Exporter
{
    public function title(): string
    {
        return 'Items';
    }

    public function columns(): array
    {
        return [
            'Code' => fn (Item $item): string => $item->code,
            'Name' => fn (Item $item): string => $item->translate('name'),
            'Type' => fn (Item $item): string => $item->type->label(),
            'Category' => fn (Item $item): ?string => $item->category?->translate('name'),
            'Unit' => fn (Item $item): ?string => $item->unit?->translate('name'),
            'Barcode' => fn (Item $item): ?string => $item->barcode,
            'Sale price' => fn (Item $item): string => (string) $item->sale_price,
            'Purchase price' => fn (Item $item): string => (string) $item->purchase_price,
            'On hand' => fn (Item $item): ?string => $item->isStock() ? (string) ($item->getAttribute('stock_levels_sum_quantity') ?? '0') : null,
            'Reorder level' => fn (Item $item): ?string => $item->reorder_level !== null ? (string) $item->reorder_level : null,
            'Status' => fn (Item $item): string => $item->is_active ? __('Active') : __('Inactive'),
        ];
    }
}
