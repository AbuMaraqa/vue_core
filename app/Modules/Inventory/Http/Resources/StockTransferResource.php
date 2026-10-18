<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransfer
 */
class StockTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date->toDateString(),
            'from_warehouse' => $this->whenLoaded('fromWarehouse', fn (): string => $this->fromWarehouse->label()),
            'to_warehouse' => $this->whenLoaded('toWarehouse', fn (): string => $this->toWarehouse->label()),
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'created_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'lines_count' => $this->whenCounted('lines'),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (StockTransferLine $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item' => $line->relationLoaded('item') ? $line->item->label() : null,
                'unit' => $line->relationLoaded('item') ? $line->item->unit?->translate('name') : null,
                'quantity' => (string) $line->quantity,
            ])->values()->all()),
            'can' => [
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
            ],
        ];
    }
}
