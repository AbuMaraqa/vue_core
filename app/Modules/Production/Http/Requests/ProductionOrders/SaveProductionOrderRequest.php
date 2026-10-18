<?php

namespace App\Modules\Production\Http\Requests\ProductionOrders;

use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Production\Data\ProductionOrderData;
use App\Modules\Production\Http\Requests\Concerns\ValidatesProductionLines;
use App\Modules\Production\Models\BillOfMaterials;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update a draft production order: a stock product and quantity,
 * active warehouses, at least one material and optional other costs.
 */
class SaveProductionOrderRequest extends FormRequest
{
    use ValidatesProductionLines;

    public function authorize(): bool
    {
        $order = $this->order();

        return $order ? $this->user()->can('update', $order) : $this->user()->can('create', ProductionOrder::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $warehouse = Rule::exists(Warehouse::class, 'id')->where('is_active', true);

        return [
            'date' => ['required', 'date'],
            'bill_of_materials_id' => ['nullable', 'integer', Rule::exists(BillOfMaterials::class, 'id')],
            'item_id' => ['required', 'integer', $this->stockItemRule()],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'materials_warehouse_id' => ['required', 'integer', $warehouse],
            'product_warehouse_id' => ['required', 'integer', $warehouse],
            'contact_id' => ['nullable', 'integer', Rule::exists(Contact::class, 'id')],
            'cost_center_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id')],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'materials' => ['required', 'array', 'min:1', 'max:200'],
            'materials.*.item_id' => ['required', 'integer', 'distinct', $this->stockItemRule()],
            'materials.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            ...$this->costRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->rejectProductAsMaterial($validator, 'materials', $this->integer('item_id')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'item_id' => __('Product'),
            'quantity' => __('Quantity to produce'),
            'bill_of_materials_id' => __('Bill of materials'),
            'materials_warehouse_id' => __('Materials warehouse'),
            'product_warehouse_id' => __('Finished goods warehouse'),
            'contact_id' => __('Customer'),
            'cost_center_id' => __('Cost center'),
            'materials' => __('Materials'),
            ...$this->lineAttributes('materials'),
        ];
    }

    public function toData(): ProductionOrderData
    {
        return ProductionOrderData::from([
            ...$this->validated(),
            'costs' => $this->validated('costs') ?? [],
        ]);
    }

    protected function order(): ?ProductionOrder
    {
        $order = $this->route('order');

        return $order instanceof ProductionOrder ? $order : null;
    }
}
