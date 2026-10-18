<?php

namespace App\Modules\Banking\Http\Resources;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Banking\Models\BankVoucher;
use App\Modules\Banking\Models\BankVoucherLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankVoucher
 */
class BankVoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'direction' => $this->direction->value,
            'direction_label' => $this->direction->label(),
            'date' => $this->date->toDateString(),
            'bank_account' => $this->whenLoaded('bankAccount', fn (): string => $this->bankAccount->label()),
            'contact' => $this->whenLoaded('contact', fn (): ?string => $this->contact?->name),
            'contact_phone' => $this->whenLoaded('contact', fn (): ?string => $this->contact->mobile ?? $this->contact?->phone),
            'payee' => $this->payee,
            'currency' => $this->whenLoaded('currency', fn (): array => ['code' => $this->currency->code, 'decimal_places' => $this->currency->decimal_places]),
            'exchange_rate' => (string) $this->exchange_rate,
            'reference' => $this->reference,
            'description' => $this->description,
            'subtotal' => (string) $this->subtotal,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'created_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (BankVoucherLine $line): array => [
                'id' => $line->id,
                'account' => $line->relationLoaded('account') ? $line->account->label() : null,
                'cost_center' => $line->relationLoaded('costCenter') ? $line->costCenter?->label() : null,
                'tax' => $line->relationLoaded('tax') ? $line->tax?->translate('name') : null,
                'description' => $line->description,
                'amount' => (string) $line->amount,
                'tax_amount' => (string) $line->tax_amount,
            ])->values()->all()),
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
