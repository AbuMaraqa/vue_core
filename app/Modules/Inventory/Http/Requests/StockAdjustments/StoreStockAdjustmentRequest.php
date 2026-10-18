<?php

namespace App\Modules\Inventory\Http\Requests\StockAdjustments;

use App\Modules\Accounting\Models\Account;
use App\Modules\Inventory\Data\StockAdjustmentData;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A stock adjustment: one line per stock item, with a quantity change that
 * is not zero.
 */
class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockAdjustment::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer', Rule::exists(Warehouse::class, 'id')->where('is_active', true)],
            'account_id' => ['nullable', 'integer', Rule::exists(Account::class, 'id')->where('is_group', false)],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists(Item::class, 'id')->where('type', ItemType::Stock->value)],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,4', 'not_in:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,6'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('lines') as $index => $line) {
                    if (is_array($line) && (float) ($line['quantity'] ?? 0) === 0.0) {
                        $validator->errors()->add("lines.{$index}.quantity", __('Enter a quantity to add or remove.'));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'warehouse_id' => __('Warehouse'),
            'account_id' => __('Adjustment account'),
            'lines.*.item_id' => __('Item'),
            'lines.*.quantity' => __('Quantity'),
            'lines.*.unit_cost' => __('Unit cost'),
        ];
    }

    public function toData(): StockAdjustmentData
    {
        return StockAdjustmentData::from($this->validated());
    }
}
