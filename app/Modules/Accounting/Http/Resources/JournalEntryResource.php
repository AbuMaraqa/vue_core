<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Support\DocumentLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntry
 */
class JournalEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date->toDateString(),
            'reference' => $this->reference,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', fn (): array => [
                'code' => $this->currency->code,
                'decimal_places' => $this->currency->decimal_places,
            ]),
            'exchange_rate' => (string) $this->exchange_rate,
            'total' => (string) $this->total,
            'base_total' => (string) $this->base_total,
            'source' => $this->source_type ? [
                'type' => class_basename($this->source_type),
                'id' => $this->source_id,
                'label' => $this->sourceLabel(),
                'document' => $this->relationLoaded('source') && $this->source !== null ? app(DocumentLinks::class)->for($this->source) : null,
            ] : null,
            'reversal_of' => $this->whenLoaded('reversalOf', fn (): ?array => $this->reversalOf ? ['id' => $this->reversalOf->id, 'number' => $this->reversalOf->number] : null),
            'reversed_by' => $this->whenLoaded('reversedBy', fn (): ?array => $this->reversedBy ? ['id' => $this->reversedBy->id, 'number' => $this->reversedBy->number] : null),
            'created_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'posted_by' => $this->whenLoaded('poster', fn (): ?string => $this->poster?->name),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (JournalLine $line): array => [
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account' => $line->relationLoaded('account') ? $line->account->label() : null,
                'cost_center_id' => $line->cost_center_id,
                'cost_center' => $line->relationLoaded('costCenter') ? $line->costCenter?->label() : null,
                'description' => $line->description,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'base_debit' => (string) $line->base_debit,
                'base_credit' => (string) $line->base_credit,
            ])->values()->all()),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'post' => $request->user()?->can('post', $this->resource) ?? false,
                'reverse' => $request->user()?->can('reverse', $this->resource) ?? false,
            ],
        ];
    }

    /**
     * The number of the document that wrote the entry, when it has one.
     */
    protected function sourceLabel(): ?string
    {
        $source = $this->source;

        if ($source === null) {
            return null;
        }

        $number = $source->getAttribute('number') ?? $source->getAttribute('name');

        return is_scalar($number) ? (string) $number : null;
    }
}
