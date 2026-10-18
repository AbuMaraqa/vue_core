<?php

namespace App\Modules\Inventory\Http\Requests\StockTransfers;

use App\Modules\Inventory\Data\StockTransferData;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A stock transfer between two different active warehouses.
 */
class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockTransfer::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $activeWarehouse = Rule::exists(Warehouse::class, 'id')->where('is_active', true);

        return [
            'date' => ['required', 'date'],
            'from_warehouse_id' => ['required', 'integer', $activeWarehouse],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', $activeWarehouse],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists(Item::class, 'id')->where('type', ItemType::Stock->value)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_warehouse_id' => __('From warehouse'),
            'to_warehouse_id' => __('To warehouse'),
            'lines.*.item_id' => __('Item'),
            'lines.*.quantity' => __('Quantity'),
        ];
    }

    public function toData(): StockTransferData
    {
        return StockTransferData::from($this->validated());
    }
}
