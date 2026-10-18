<?php

namespace App\Modules\Assets\Http\Resources;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetDepreciation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asset
 */
class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn (): string => $this->category->translate('name')),
            'serial_number' => $this->serial_number,
            'location' => $this->location,
            'cost_center_id' => $this->cost_center_id,
            'cost_center' => $this->whenLoaded('costCenter', fn (): ?string => $this->costCenter?->label()),
            'acquisition_date' => $this->acquisition_date->toDateString(),
            'depreciation_start_date' => $this->depreciation_start_date->toDateString(),
            'cost' => (string) $this->cost,
            'salvage_value' => (string) $this->salvage_value,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'useful_life_months' => $this->useful_life_months,
            'declining_rate' => $this->declining_rate !== null ? (string) $this->declining_rate : null,
            'opening_depreciation' => (string) $this->opening_depreciation,
            'accumulated_depreciation' => (string) $this->accumulated_depreciation,
            'book_value' => $this->bookValue(),
            'depreciated_until' => $this->depreciated_until?->toDateString(),
            'funding_account' => $this->whenLoaded('fundingAccount', fn (): ?string => $this->fundingAccount?->label()),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'disposal_date' => $this->disposal_date?->toDateString(),
            'disposal_proceeds' => $this->disposal_proceeds !== null ? (string) $this->disposal_proceeds : null,
            'notes' => $this->notes,
            'depreciations' => $this->whenLoaded('depreciations', fn (): array => $this->depreciations->map(fn (AssetDepreciation $depreciation): array => [
                'id' => $depreciation->id,
                'from_date' => $depreciation->from_date->toDateString(),
                'to_date' => $depreciation->to_date->toDateString(),
                'months' => $depreciation->months,
                'amount' => (string) $depreciation->amount,
                'run' => $depreciation->run ? ['id' => $depreciation->run->id, 'number' => $depreciation->run->number] : null,
            ])->values()->all()),
            'journal_entries' => $this->whenLoaded('journalEntries', fn (): array => $this->journalEntries
                ->map(fn (JournalEntry $entry): array => ['id' => $entry->id, 'number' => $entry->number, 'description' => $entry->description])
                ->values()
                ->all()),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'dispose' => $request->user()?->can('dispose', $this->resource) ?? false,
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
            ],
        ];
    }
}
