<?php

namespace App\Modules\Contacts\Http\Resources;

use App\Modules\Contacts\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array. `balance` is present when the
     * controller computed it (see ContactBalances).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'is_customer' => $this->is_customer,
            'is_vendor' => $this->is_vendor,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'tax_number' => $this->tax_number,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', fn (): ?string => $this->currency?->code),
            'receivable_account_id' => $this->receivable_account_id,
            'receivable_account' => $this->whenLoaded('receivableAccount', fn (): ?string => $this->receivableAccount?->label()),
            'payable_account_id' => $this->payable_account_id,
            'payable_account' => $this->whenLoaded('payableAccount', fn (): ?string => $this->payableAccount?->label()),
            'payment_terms_days' => $this->payment_terms_days,
            'credit_limit' => $this->credit_limit !== null ? (string) $this->credit_limit : null,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'balance' => $this->whenHas('balance'),
            'created_at' => $this->created_at?->toIso8601String(),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
