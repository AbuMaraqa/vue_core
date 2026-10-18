<?php

namespace App\Modules\Production\Support;

use App\Modules\Inventory\Support\InventoryOptions;
use App\Modules\Production\Models\BillOfMaterials;
use App\Modules\Production\Models\BillOfMaterialsComponent;
use App\Modules\Production\Models\BillOfMaterialsCost;

/**
 * Option lists for production forms, already translated.
 */
class ProductionOptions
{
    public function __construct(protected InventoryOptions $inventory) {}

    /**
     * The active recipes with what an order form needs to copy them: the
     * product, the batch size, the materials (waste included) and the costs.
     *
     * @return list<array<string, mixed>>
     */
    public function billsOfMaterials(): array
    {
        return array_values(BillOfMaterials::query()
            ->with(['item.unit', 'components.item.unit', 'costs'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (BillOfMaterials $bom): array => [
                'value' => $bom->id,
                'label' => $bom->label(),
                'hint' => $bom->item->label(),
                'item' => $this->inventory->itemOption($bom->item),
                'quantity' => (string) $bom->quantity,
                'components' => $bom->components->map(fn (BillOfMaterialsComponent $component): array => [
                    'item' => $this->inventory->itemOption($component->item),
                    'quantity' => $component->grossQuantity(),
                ])->values()->all(),
                'costs' => $bom->costs->map(fn (BillOfMaterialsCost $cost): array => [
                    'description' => $cost->description,
                    'account_id' => $cost->account_id,
                    'amount' => (string) $cost->amount,
                ])->values()->all(),
            ])
            ->all());
    }
}
