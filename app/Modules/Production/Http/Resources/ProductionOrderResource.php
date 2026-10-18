<?php

namespace App\Modules\Production\Http\Resources;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Inventory\Support\InventoryOptions;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderCost;
use App\Modules\Production\Models\ProductionOrderMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionOrder
 */
class ProductionOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $options = app(InventoryOptions::class);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date->toDateString(),
            'bill_of_materials_id' => $this->bill_of_materials_id,
            'bill_of_materials' => $this->whenLoaded('billOfMaterials', fn (): ?string => $this->billOfMaterials?->label()),
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', fn (): string => $this->item->label()),
            'item_option' => $this->whenLoaded('item', fn (): array => $options->itemOption($this->item)),
            'unit' => $this->whenLoaded('item', fn (): ?string => $this->item->unit?->translate('name')),
            'quantity' => (string) $this->quantity,
            'produced_quantity' => $this->produced_quantity === null ? null : (string) $this->produced_quantity,
            'materials_warehouse_id' => $this->materials_warehouse_id,
            'materials_warehouse' => $this->whenLoaded('materialsWarehouse', fn (): string => $this->materialsWarehouse->label()),
            'product_warehouse_id' => $this->product_warehouse_id,
            'product_warehouse' => $this->whenLoaded('productWarehouse', fn (): string => $this->productWarehouse->label()),
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn (): ?array => $this->contact ? ['value' => $this->contact->id, 'label' => $this->contact->label()] : null),
            'cost_center_id' => $this->cost_center_id,
            'cost_center' => $this->whenLoaded('costCenter', fn (): ?string => $this->costCenter?->label()),
            'reference' => $this->reference,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'started_on' => $this->started_on?->toDateString(),
            'completed_on' => $this->completed_on?->toDateString(),
            'materials_cost' => (string) $this->materials_cost,
            'other_costs' => (string) $this->other_costs,
            'total_cost' => (string) $this->total_cost,
            'unit_cost' => (string) $this->unit_cost,
            'created_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'materials' => $this->whenLoaded('materials', fn (): array => $this->materials->map(fn (ProductionOrderMaterial $material): array => [
                'id' => $material->id,
                'item_id' => $material->item_id,
                'item' => $material->relationLoaded('item') ? $options->itemOption($material->item) : null,
                'quantity' => (string) $material->quantity,
                'unit_cost' => (string) $material->unit_cost,
                'issued_value' => (string) $material->issued_value,
                'consumed_quantity' => $material->consumed_quantity === null ? null : (string) $material->consumed_quantity,
                'value' => (string) $material->value,
            ])->values()->all()),
            'costs' => $this->whenLoaded('costs', fn (): array => $this->costs->map(fn (ProductionOrderCost $cost): array => [
                'id' => $cost->id,
                'description' => $cost->description,
                'account_id' => $cost->account_id,
                'account' => $cost->relationLoaded('account') ? $cost->account->label() : null,
                'amount' => (string) $cost->amount,
            ])->values()->all()),
            'journal_entries' => $this->whenLoaded('journalEntries', fn (): array => $this->journalEntries
                ->sortBy('id')
                ->map(fn (JournalEntry $entry): array => ['id' => $entry->id, 'number' => $entry->number, 'description' => $entry->description])
                ->values()
                ->all()),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'start' => $request->user()?->can('start', $this->resource) ?? false,
                'complete' => $request->user()?->can('complete', $this->resource) ?? false,
                'reverse' => $request->user()?->can('reverse', $this->resource) ?? false,
            ],
        ];
    }
}
