<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array. `on_hand` and `stock_value` are
     * present when the query added them (`withSum` of the stock levels).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'name' => $this->translate('name'),
            'name_translations' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn (): ?string => $this->category?->translate('name')),
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', fn (): ?string => $this->unit?->translate('name')),
            'sale_price' => (string) $this->sale_price,
            'purchase_price' => (string) $this->purchase_price,
            'sales_tax_id' => $this->sales_tax_id,
            'purchase_tax_id' => $this->purchase_tax_id,
            'sales_account_id' => $this->sales_account_id,
            'purchase_account_id' => $this->purchase_account_id,
            'inventory_account_id' => $this->inventory_account_id,
            'cost_of_sales_account_id' => $this->cost_of_sales_account_id,
            'reorder_level' => $this->reorder_level !== null ? (string) $this->reorder_level : null,
            'is_active' => $this->is_active,
            'on_hand' => $this->whenHas('stock_levels_sum_quantity', fn (): string => Decimal::round((string) ($this->getAttribute('stock_levels_sum_quantity') ?? '0'))),
            'stock_value' => $this->whenHas('stock_levels_sum_value', fn (): string => Decimal::round((string) ($this->getAttribute('stock_levels_sum_value') ?? '0'))),
            'created_at' => $this->created_at?->toIso8601String(),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
