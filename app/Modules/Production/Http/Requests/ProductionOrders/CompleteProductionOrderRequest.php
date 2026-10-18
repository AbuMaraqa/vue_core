<?php

namespace App\Modules\Production\Http\Requests\ProductionOrders;

use App\Modules\Production\Data\ProductionCompletionData;
use App\Modules\Production\Http\Requests\Concerns\ValidatesProductionLines;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderMaterial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Finish an order: the quantity made, what was used of each issued material
 * (zero sends it all back), materials added on the way, and the final other
 * costs.
 */
class CompleteProductionOrderRequest extends FormRequest
{
    use ValidatesProductionLines;

    public function authorize(): bool
    {
        return $this->user()->can('complete', $this->order());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'produced_quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'materials' => ['required', 'array', 'min:1', 'max:200'],
            'materials.*.id' => ['nullable', 'integer', 'distinct', Rule::exists(ProductionOrderMaterial::class, 'id')->where('production_order_id', $this->order()->id)],
            'materials.*.item_id' => ['required', 'integer', $this->stockItemRule(activeOnly: false)],
            'materials.*.consumed_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            ...$this->costRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectProductAsMaterial($validator, 'materials', $this->order()->item_id);

                foreach ((array) $this->input('materials') as $index => $line) {
                    if (is_array($line) && blank($line['id'] ?? null) && (float) ($line['consumed_quantity'] ?? 0) <= 0) {
                        $validator->errors()->add("materials.{$index}.consumed_quantity", __('Enter the quantity used of the added material.'));
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
            'date' => __('Completion date'),
            'produced_quantity' => __('Produced quantity'),
            'materials' => __('Materials'),
            'materials.*.consumed_quantity' => __('Quantity used'),
            ...$this->lineAttributes('materials'),
        ];
    }

    public function toData(): ProductionCompletionData
    {
        return ProductionCompletionData::from([
            'date' => $this->validated('date'),
            'produced_quantity' => (string) $this->validated('produced_quantity'),
            'materials' => array_map(fn (array $line): array => [
                'id' => isset($line['id']) ? (int) $line['id'] : null,
                'item_id' => (int) $line['item_id'],
                'consumed_quantity' => (string) $line['consumed_quantity'],
            ], $this->validated('materials')),
            'costs' => $this->validated('costs') ?? [],
        ]);
    }

    protected function order(): ProductionOrder
    {
        $order = $this->route('order');

        abort_unless($order instanceof ProductionOrder, 404);

        return $order;
    }
}
