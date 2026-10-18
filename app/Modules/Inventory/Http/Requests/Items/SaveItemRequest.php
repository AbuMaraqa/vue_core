<?php

namespace App\Modules\Inventory\Http\Requests\Items;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * Create or update an item. Its own accounts, when chosen, must be of the
 * right kind: revenue for sales, an inventory asset, a cost of sales, and an
 * expense for what a service costs to buy. An item that has moved in stock
 * keeps its type.
 */
class SaveItemRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $item = $this->item();

        return $item ? $this->user()->can('update', $item) : $this->user()->can('create', Item::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postable = fn (): Exists => Rule::exists(Account::class, 'id')->where('is_group', false);

        return [
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[0-9A-Za-z.\-_\/]+$/', Rule::unique(Item::class, 'code')->ignore($this->item())],
            'barcode' => ['nullable', 'string', 'max:100'],
            ...$this->translationRules('name', 150),
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(ItemType::class)],
            'category_id' => ['nullable', 'integer', Rule::exists(ItemCategory::class, 'id')],
            'unit_id' => ['nullable', 'integer', Rule::exists(Unit::class, 'id')],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'sales_tax_id' => ['nullable', 'integer', Rule::exists(Tax::class, 'id')->whereIn('scope', [TaxScope::Sales->value, TaxScope::Both->value])],
            'purchase_tax_id' => ['nullable', 'integer', Rule::exists(Tax::class, 'id')->whereIn('scope', [TaxScope::Purchases->value, TaxScope::Both->value])],
            'sales_account_id' => ['nullable', 'integer', $postable()->where('type', AccountType::Revenue->value)],
            'purchase_account_id' => ['nullable', 'integer', $postable()->where('type', AccountType::Expense->value)],
            'inventory_account_id' => ['nullable', 'integer', $postable()->where('subtype', AccountSubtype::Inventory->value)],
            'cost_of_sales_account_id' => ['nullable', 'integer', $postable()->where('type', AccountType::Expense->value)],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $item = $this->item();

                if ($item && $item->type->value !== $this->input('type') && $item->movements()->exists()) {
                    $validator->errors()->add('type', __('The type cannot change once the item has moved in stock.'));
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
            'code' => __('Code'),
            'type' => __('Type'),
            'category_id' => __('Category'),
            'unit_id' => __('Unit'),
            'sale_price' => __('Sale price'),
            'purchase_price' => __('Purchase price'),
            'sales_tax_id' => __('Sales tax'),
            'purchase_tax_id' => __('Purchase tax'),
            'sales_account_id' => __('Sales account'),
            'purchase_account_id' => __('Purchase account'),
            'inventory_account_id' => __('Inventory account'),
            'cost_of_sales_account_id' => __('Cost of sales account'),
            'reorder_level' => __('Reorder level'),
        ];
    }

    /**
     * The validated attributes, ready to save. An item saved without a code
     * keeps its current one, or gets a temporary one the controller replaces
     * with `I-00042` from the id once the item exists.
     *
     * @return array<string, mixed>
     */
    public function attributesToSave(): array
    {
        $validated = $this->validated();

        return [
            ...$validated,
            'code' => filled($validated['code'] ?? null)
                ? $validated['code']
                : ($this->item()?->code ?? Item::TEMPORARY_CODE_PREFIX.Str::random(20)),
            'name' => array_filter($validated['name']),
            'sale_price' => $validated['sale_price'] ?? 0,
            'purchase_price' => $validated['purchase_price'] ?? 0,
            'is_active' => $this->boolean('is_active', true),
        ];
    }

    public function item(): ?Item
    {
        $item = $this->route('item');

        return $item instanceof Item ? $item : null;
    }
}
