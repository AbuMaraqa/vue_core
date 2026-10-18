<?php

namespace App\Modules\Production\Http\Resources;

use App\Modules\Inventory\Support\InventoryOptions;
use App\Modules\Production\Models\BillOfMaterials;
use App\Modules\Production\Models\BillOfMaterialsComponent;
use App\Modules\Production\Models\BillOfMaterialsCost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillOfMaterials
 */
class BillOfMaterialsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $options = app(InventoryOptions::class);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', fn (): string => $this->item->label()),
            'item_option' => $this->whenLoaded('item', fn (): array => $options->itemOption($this->item)),
            'unit' => $this->whenLoaded('item', fn (): ?string => $this->item->unit?->translate('name')),
            'quantity' => (string) $this->quantity,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'components_count' => $this->whenCounted('components'),
            'components' => $this->whenLoaded('components', fn (): array => $this->components->map(fn (BillOfMaterialsComponent $component): array => [
                'id' => $component->id,
                'item_id' => $component->item_id,
                'item' => $component->relationLoaded('item') ? $options->itemOption($component->item) : null,
                'quantity' => (string) $component->quantity,
                'waste_percent' => (string) $component->waste_percent,
                'gross_quantity' => $component->grossQuantity(),
            ])->values()->all()),
            'costs' => $this->whenLoaded('costs', fn (): array => $this->costs->map(fn (BillOfMaterialsCost $cost): array => [
                'id' => $cost->id,
                'description' => $cost->description,
                'account_id' => $cost->account_id,
                'account' => $cost->relationLoaded('account') ? $cost->account->label() : null,
                'amount' => (string) $cost->amount,
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
