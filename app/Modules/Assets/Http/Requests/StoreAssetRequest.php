<?php

namespace App\Modules\Assets\Http\Requests;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Register an asset. Salvage value and depreciation already taken must
 * leave something to depreciate.
 */
class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Asset::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists(AssetCategory::class, 'id')],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'cost_center_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id')->where('is_active', true)],
            'acquisition_date' => ['required', 'date'],
            'depreciation_start_date' => ['nullable', 'date', 'after_or_equal:acquisition_date'],
            'cost' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'salvage_value' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'method' => ['nullable', Rule::enum(DepreciationMethod::class)],
            'useful_life_months' => ['nullable', 'integer', 'between:1,1200'],
            'declining_rate' => ['nullable', 'numeric', 'gt:0', 'max:100', 'decimal:0,4'],
            'opening_depreciation' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'funding_account_id' => ['nullable', 'integer', Rule::exists(Account::class, 'id')->where('is_group', false)->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! is_numeric($this->input('cost'))) {
                    return;
                }

                $left = Decimal::of((string) $this->input('cost'))
                    ->minus(is_numeric($this->input('salvage_value')) ? (string) $this->input('salvage_value') : '0')
                    ->minus(is_numeric($this->input('opening_depreciation')) ? (string) $this->input('opening_depreciation') : '0');

                if ($left->isNegative()) {
                    $validator->errors()->add('salvage_value', __('The salvage value and the depreciation already taken cannot exceed the cost.'));
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
            'category_id' => __('Category'),
            'acquisition_date' => __('Acquisition date'),
            'depreciation_start_date' => __('Depreciation start'),
            'salvage_value' => __('Salvage value'),
            'useful_life_months' => __('Useful life'),
            'declining_rate' => __('Yearly rate'),
            'opening_depreciation' => __('Depreciation already taken'),
            'funding_account_id' => __('Paid from'),
        ];
    }
}
