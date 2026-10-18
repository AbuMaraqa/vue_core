<?php

namespace App\Modules\Invoicing\Http\Resources;

use App\Modules\Invoicing\Enums\QuotationStatus;
use App\Modules\Invoicing\Models\Quotation;
use App\Modules\Invoicing\Models\QuotationLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
class QuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $displayStatus = $this->displayStatus();
        $user = $request->user();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn (): array => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'code' => $this->contact->code,
                'tax_number' => $this->contact->tax_number,
                'address' => $this->contact->address,
            ]),
            'date' => $this->date->toDateString(),
            'valid_until' => $this->valid_until->toDateString(),
            'reference' => $this->reference,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', fn (): array => ['code' => $this->currency->code, 'decimal_places' => $this->currency->decimal_places]),
            'prices_include_tax' => $this->prices_include_tax,
            'subtotal' => (string) $this->subtotal,
            'discount_total' => (string) $this->discount_total,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'status' => $displayStatus->value,
            'status_label' => $displayStatus->label(),
            'status_color' => $displayStatus->color(),
            'is_expired' => $this->isExpired(),
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoice', fn (): ?array => $this->invoice ? [
                'id' => $this->invoice->id,
                'number' => $this->invoice->number,
                'status' => $this->invoice->status->value,
            ] : null),
            'created_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (QuotationLine $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item' => $line->relationLoaded('item') && $line->item ? ['code' => $line->item->code, 'type' => $line->item->type->value, 'unit' => $line->item->unit?->translate('name')] : null,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'discount_percent' => (string) $line->discount_percent,
                'tax_id' => $line->tax_id,
                'tax_rate' => (string) $line->tax_rate,
                'discount_amount' => (string) $line->discount_amount,
                'subtotal' => (string) $line->subtotal,
                'tax_amount' => (string) $line->tax_amount,
                'total' => (string) $line->total,
            ])->values()->all()),
            'transitions' => $user?->can('changeStatus', $this->resource)
                ? array_map(fn (QuotationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ], $this->status->transitions())
                : [],
            'can' => [
                'update' => $user?->can('update', $this->resource) ?? false,
                'delete' => $user?->can('delete', $this->resource) ?? false,
                'convert' => $user?->can('convert', $this->resource) ?? false,
            ],
        ];
    }
}
