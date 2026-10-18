<?php

namespace App\Modules\Invoicing\Http\Resources;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Invoicing\Models\Allocation;
use App\Modules\Invoicing\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'number' => $this->number,
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn (): string => $this->contact->name),
            'contact_phone' => $this->whenLoaded('contact', fn (): ?string => $this->contact->mobile ?? $this->contact->phone),
            'date' => $this->date->toDateString(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'bank_account' => $this->whenLoaded('bankAccount', fn (): ?string => $this->bankAccount?->label()),
            'cheque' => $this->whenLoaded('cheque', fn (): ?array => $this->cheque ? [
                'id' => $this->cheque->id,
                'number' => $this->cheque->number,
                'due_date' => $this->cheque->due_date->toDateString(),
                'status_label' => $this->cheque->status->label(),
                'status_color' => $this->cheque->status->color(),
            ] : null),
            'amount' => (string) $this->amount,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', fn (): array => ['code' => $this->currency->code, 'decimal_places' => $this->currency->decimal_places]),
            'exchange_rate' => (string) $this->exchange_rate,
            'reference' => $this->reference,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_effective' => $this->whenLoaded('cheque', fn (): bool => $this->isEffective()),
            'allocated' => $this->whenLoaded('allocations', fn (): string => $this->allocatedAmount()),
            'unallocated' => $this->whenLoaded('allocations', fn (): string => $this->unallocatedAmount()),
            'allocations' => $this->whenLoaded('allocations', fn (): array => $this->allocations->map(fn (Allocation $allocation): array => [
                'id' => $allocation->id,
                'date' => $allocation->date->toDateString(),
                'amount' => (string) $allocation->amount,
                'invoice' => $allocation->relationLoaded('invoice') ? [
                    'id' => $allocation->invoice->id,
                    'number' => $allocation->invoice->number,
                    'date' => $allocation->invoice->date->toDateString(),
                    'total' => (string) $allocation->invoice->total,
                ] : null,
            ])->values()->all()),
            'journal_entries' => $this->whenLoaded('journalEntries', fn (): array => $this->journalEntries
                ->map(fn (JournalEntry $entry): array => ['id' => $entry->id, 'number' => $entry->number, 'status' => $entry->status->value])
                ->values()
                ->all()),
            'created_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'can' => [
                'allocate' => $request->user()?->can('allocate', $this->resource) ?? false,
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
            ],
        ];
    }
}
