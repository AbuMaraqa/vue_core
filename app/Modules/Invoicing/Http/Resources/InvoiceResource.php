<?php

namespace App\Modules\Invoicing\Http\Resources;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Invoicing\Models\Allocation;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paymentStatus = $this->paymentStatus();

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'number' => $this->number,
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn (): array => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'code' => $this->contact->code,
                'tax_number' => $this->contact->tax_number,
                'address' => $this->contact->address,
                'phone' => $this->contact->mobile ?? $this->contact->phone,
            ]),
            'date' => $this->date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'reference' => $this->reference,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', fn (): array => ['code' => $this->currency->code, 'decimal_places' => $this->currency->decimal_places]),
            'exchange_rate' => (string) $this->exchange_rate,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn (): ?string => $this->warehouse?->label()),
            'cost_center_id' => $this->cost_center_id,
            'original_invoice' => $this->whenLoaded('originalInvoice', fn (): ?array => $this->originalInvoice ? ['id' => $this->originalInvoice->id, 'number' => $this->originalInvoice->number] : null),
            'prices_include_tax' => $this->prices_include_tax,
            'is_opening' => $this->is_opening,
            'subtotal' => (string) $this->subtotal,
            'discount_total' => (string) $this->discount_total,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'paid_amount' => (string) $this->paid_amount,
            'amount_due' => $this->amountDue(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'payment_status' => $paymentStatus->value,
            'payment_status_label' => $paymentStatus->label(),
            'payment_status_color' => $paymentStatus->color(),
            'is_overdue' => $this->isOverdue(),
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (InvoiceLine $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item' => $line->relationLoaded('item') && $line->item ? ['code' => $line->item->code, 'type' => $line->item->type->value, 'unit' => $line->item->unit?->translate('name')] : null,
                'description' => $line->description,
                'account_id' => $line->account_id,
                'account' => $line->relationLoaded('account') ? $line->account->label() : null,
                'cost_center_id' => $line->cost_center_id,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'discount_percent' => (string) $line->discount_percent,
                'tax_id' => $line->tax_id,
                'tax_rate' => (string) $line->tax_rate,
                'discount_amount' => (string) $line->discount_amount,
                'subtotal' => (string) $line->subtotal,
                'tax_amount' => (string) $line->tax_amount,
                'total' => (string) $line->total,
                'unit_cost' => $line->unit_cost !== null ? (string) $line->unit_cost : null,
            ])->values()->all()),
            'allocations' => $this->whenLoaded('allocations', fn (): array => $this->allocations->map(fn (Allocation $allocation): array => [
                'id' => $allocation->id,
                'date' => $allocation->date->toDateString(),
                'amount' => (string) $allocation->amount,
                'payment' => $allocation->payment ? ['id' => $allocation->payment->id, 'number' => $allocation->payment->number, 'type' => $allocation->payment->type->value] : null,
                'credit_note' => $allocation->creditNote ? ['id' => $allocation->creditNote->id, 'number' => $allocation->creditNote->number, 'type' => $allocation->creditNote->type->value] : null,
                'exchange_difference' => $allocation->journalEntry ? ['id' => $allocation->journalEntry->id, 'number' => $allocation->journalEntry->number] : null,
            ])->values()->all()),
            'applied_to' => $this->whenLoaded('appliedTo', fn (): array => $this->appliedTo->map(fn (Allocation $allocation): array => [
                'id' => $allocation->id,
                'date' => $allocation->date->toDateString(),
                'amount' => (string) $allocation->amount,
                'invoice' => ['id' => $allocation->invoice->id, 'number' => $allocation->invoice->number, 'type' => $allocation->invoice->type->value],
            ])->values()->all()),
            'journal_entries' => $this->whenLoaded('journalEntries', fn (): array => $this->journalEntries
                ->map(fn (JournalEntry $entry): array => ['id' => $entry->id, 'number' => $entry->number, 'description' => $entry->description, 'status' => $entry->status->value])
                ->values()
                ->all()),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'post' => $request->user()?->can('post', $this->resource) ?? false,
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
                'settle' => $request->user()?->can('settle', $this->resource) ?? false,
            ],
        ];
    }
}
