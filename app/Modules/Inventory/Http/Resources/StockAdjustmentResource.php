<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockAdjustment
 */
class StockAdjustmentResource extends JsonResource
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
            'warehouse' => $this->whenLoaded('warehouse', fn (): string => $this->warehouse->label()),
            'account' => $this->whenLoaded('account', fn (): string => $this->account->label()),
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'created_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'total' => $this->whenLoaded('lines', fn (): string => Decimal::round(Decimal::sum($this->lines->pluck('value')))),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (StockAdjustmentLine $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item' => $line->relationLoaded('item') ? $line->item->label() : null,
                'quantity' => (string) $line->quantity,
                'unit_cost' => (string) $line->unit_cost,
                'value' => (string) $line->value,
            ])->values()->all()),
            'journal_entries' => $this->whenLoaded('journalEntries', fn (): array => $this->journalEntries
                ->map(fn (JournalEntry $entry): array => ['id' => $entry->id, 'number' => $entry->number])
                ->values()
                ->all()),
            'can' => [
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
            ],
        ];
    }
}
