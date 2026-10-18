<?php

namespace App\Modules\Production\Support;

use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Support\StockLedger;
use App\Modules\Production\Models\BillOfMaterials;
use App\Modules\Production\Models\BillOfMaterialsComponent;
use Illuminate\Support\Collection;

/**
 * What a recipe should cost today: each material at its average cost in all
 * warehouses (its purchase price when it has no stock), waste included,
 * plus the other costs. Only a guide for pricing; the real cost of a product
 * is set by the production order that makes it.
 */
class CostEstimator
{
    /**
     * @return array{materials: string, other: string, total: string, unit: string}
     */
    public function estimate(BillOfMaterials $bom): array
    {
        $costs = $this->averageCosts(array_values($bom->components->map(fn (BillOfMaterialsComponent $component): int => $component->item_id)->all()));

        $materials = Decimal::sum($bom->components->map(fn (BillOfMaterialsComponent $component): string => Decimal::multiply(
            $component->grossQuantity(),
            $costs[$component->item_id] ?? '0',
        )));
        $other = Decimal::sum($bom->costs->pluck('amount'));
        $total = $materials->plus($other);

        return [
            'materials' => Decimal::round($materials),
            'other' => Decimal::round($other),
            'total' => Decimal::round($total),
            'unit' => Decimal::isPositive($bom->quantity) ? Decimal::divide($total, $bom->quantity, 4) : '0.0000',
        ];
    }

    /**
     * The average cost of each item over all warehouses, else its purchase
     * price.
     *
     * @param  list<int>  $itemIds
     * @return array<int, string>
     */
    public function averageCosts(array $itemIds): array
    {
        /** @var Collection<int, StockLevel> $levels */
        $levels = StockLevel::query()
            ->whereIn('item_id', $itemIds)
            ->selectRaw('item_id, SUM(quantity) as quantity, SUM(value) as value')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        return Item::query()
            ->findMany($itemIds)
            ->mapWithKeys(function (Item $item) use ($levels): array {
                $level = $levels->get($item->id);
                $quantity = (string) ($level?->getAttribute('quantity') ?? '0');

                return [$item->id => Decimal::isPositive($quantity)
                    ? Decimal::divide((string) $level?->getAttribute('value'), $quantity, StockLedger::COST_SCALE)
                    : (string) $item->purchase_price];
            })
            ->all();
    }
}
