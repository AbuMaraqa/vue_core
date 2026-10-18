<?php

namespace App\Modules\Production\Http\Requests\BillsOfMaterials;

use App\Modules\Production\Data\BillOfMaterialsData;
use App\Modules\Production\Http\Requests\Concerns\ValidatesProductionLines;
use App\Modules\Production\Models\BillOfMaterials;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update a recipe: a stock product, the batch size, at least one
 * material (each once, never the product itself) and optional other costs.
 */
class SaveBillOfMaterialsRequest extends FormRequest
{
    use ValidatesProductionLines;

    public function authorize(): bool
    {
        $bom = $this->bom();

        return $bom ? $this->user()->can('update', $bom) : $this->user()->can('create', BillOfMaterials::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[0-9A-Za-z.\-_\/]+$/', Rule::unique(BillOfMaterials::class, 'code')->ignore($this->bom())],
            'name' => ['required', 'string', 'max:255'],
            'item_id' => ['required', 'integer', $this->stockItemRule()],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'components' => ['required', 'array', 'min:1', 'max:200'],
            'components.*.item_id' => ['required', 'integer', 'distinct', $this->stockItemRule()],
            'components.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'components.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            ...$this->costRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->rejectProductAsMaterial($validator, 'components', $this->integer('item_id')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('Code'),
            'name' => __('Name'),
            'item_id' => __('Product'),
            'quantity' => __('Batch quantity'),
            'components' => __('Materials'),
            'components.*.waste_percent' => __('Waste %'),
            ...$this->lineAttributes('components'),
        ];
    }

    public function toData(): BillOfMaterialsData
    {
        return BillOfMaterialsData::from([
            ...$this->validated(),
            'costs' => $this->validated('costs') ?? [],
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    protected function bom(): ?BillOfMaterials
    {
        $bom = $this->route('bom');

        return $bom instanceof BillOfMaterials ? $bom : null;
    }
}
