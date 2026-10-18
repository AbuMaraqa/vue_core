<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

/**
 * Option lists for inventory forms, already translated.
 */
class InventoryOptions
{
    /**
     * @return list<array{value: int, label: string}>
     */
    public function warehouses(): array
    {
        return array_values(Warehouse::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Warehouse $warehouse): array => ['value' => $warehouse->id, 'label' => $warehouse->label()])
            ->all());
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function categories(): array
    {
        return array_values(ItemCategory::query()
            ->get()
            ->map(fn (ItemCategory $category): array => ['value' => $category->id, 'label' => $category->translate('name')])
            ->sortBy('label')
            ->all());
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function units(): array
    {
        return array_values(Unit::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (Unit $unit): array => ['value' => $unit->id, 'label' => $unit->translate('name')])
            ->sortBy('label')
            ->all());
    }

    /**
     * Active items matching a search, for the item picker of document forms.
     *
     * @return list<array<string, mixed>>
     */
    public function searchItems(string $search = '', ?ItemType $type = null, int $limit = 30): array
    {
        return array_values(Item::query()
            ->with('unit')
            ->withSum('stockLevels as on_hand', 'quantity')
            ->where('is_active', true)
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $matching) => $matching
                ->where('code', 'like', "%{$search}%")
                ->orWhere('barcode', $search)
                ->orWhere(fn (Builder $names) => collect(Item::translationColumns('name'))
                    ->each(fn (string $column) => $names->orWhere($column, 'like', "%{$search}%")))))
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->map(fn (Item $item): array => $this->itemOption($item))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function itemOption(Item $item): array
    {
        return [
            'value' => $item->id,
            'label' => $item->label(),
            'hint' => $item->unit?->translate('name'),
            'type' => $item->type->value,
            'unit' => $item->unit?->translate('name'),
            'sale_price' => (string) $item->sale_price,
            'purchase_price' => (string) $item->purchase_price,
            'sales_tax_id' => $item->sales_tax_id,
            'purchase_tax_id' => $item->purchase_tax_id,
            'on_hand' => $item->isStock() ? (string) ($item->getAttribute('on_hand') ?? '0') : null,
        ];
    }
}
