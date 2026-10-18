<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->translate('name'),
            'name_translations' => $this->name,
            'label' => $this->label(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'subtype' => $this->subtype->value,
            'subtype_label' => $this->subtype->label(),
            'is_group' => $this->is_group,
            'is_active' => $this->is_active,
            'currency_id' => $this->currency_id,
            'description' => $this->description,
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
