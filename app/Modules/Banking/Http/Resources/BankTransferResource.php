<?php

namespace App\Modules\Banking\Http\Resources;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Banking\Models\BankTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankTransfer
 */
class BankTransferResource extends JsonResource
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
            'from_account' => $this->whenLoaded('fromAccount', fn (): array => [
                'label' => $this->fromAccount->label(),
                'currency' => $this->fromAccount->currency->code,
                'decimal_places' => $this->fromAccount->currency->decimal_places,
            ]),
            'to_account' => $this->whenLoaded('toAccount', fn (): array => [
                'label' => $this->toAccount->label(),
                'currency' => $this->toAccount->currency->code,
                'decimal_places' => $this->toAccount->currency->decimal_places,
            ]),
            'amount' => (string) $this->amount,
            'to_amount' => (string) $this->to_amount,
            'fees' => (string) $this->fees,
            'reference' => $this->reference,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'created_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'journal_entries' => $this->whenLoaded('journalEntries', fn (): array => $this->journalEntries
                ->map(fn (JournalEntry $entry): array => ['id' => $entry->id, 'number' => $entry->number, 'status' => $entry->status->value])
                ->values()
                ->all()),
            'can' => [
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
            ],
        ];
    }
}
