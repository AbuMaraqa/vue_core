<?php

namespace App\Modules\Production\Http\Requests\Concerns;

use App\Modules\Accounting\Models\Account;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * Rules shared by recipes and production orders: stock items as product and
 * materials, and other cost lines on postable accounts.
 */
trait ValidatesProductionLines
{
    /**
     * A stock item; new choices must also be active.
     */
    protected function stockItemRule(bool $activeOnly = true): Exists
    {
        $rule = Rule::exists(Item::class, 'id')->where('type', ItemType::Stock->value);

        return $activeOnly ? $rule->where('is_active', true) : $rule;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function costRules(): array
    {
        return [
            'costs' => ['nullable', 'array', 'max:50'],
            'costs.*.description' => ['required', 'string', 'max:255'],
            'costs.*.account_id' => ['required', 'integer', Rule::exists(Account::class, 'id')->where('is_group', false)->where('is_active', true)],
            'costs.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
        ];
    }

    /**
     * A product cannot be made from itself.
     */
    protected function rejectProductAsMaterial(Validator $validator, string $lines, int $productId): void
    {
        foreach ((array) $this->input($lines) as $index => $line) {
            if (is_array($line) && $productId !== 0 && (int) ($line['item_id'] ?? 0) === $productId) {
                $validator->errors()->add("{$lines}.{$index}.item_id", __('The product cannot be one of its own materials.'));
            }
        }
    }

    /**
     * @return array<string, string>
     */
    protected function lineAttributes(string $lines): array
    {
        return [
            "{$lines}.*.item_id" => __('Material'),
            "{$lines}.*.quantity" => __('Quantity'),
            'costs.*.description' => __('Description'),
            'costs.*.account_id' => __('Account'),
            'costs.*.amount' => __('Amount'),
        ];
    }
}
