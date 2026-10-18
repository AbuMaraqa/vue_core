<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Item categories and the accounts their items post to.
 */
class ItemCategoryController extends Controller
{
    use ValidatesTranslations;

    public function index(AccountingOptions $options): Response
    {
        Gate::authorize('viewAny', ItemCategory::class);

        return Inertia::render('admin/inventory/categories/Index', [
            'categories' => ItemCategory::query()->withCount('items')->get()
                ->map(fn (ItemCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->translate('name'),
                    'name_translations' => $category->name,
                    'items_count' => $category->getAttribute('items_count'),
                    'inventory_account_id' => $category->inventory_account_id,
                    'cost_of_sales_account_id' => $category->cost_of_sales_account_id,
                    'sales_account_id' => $category->sales_account_id,
                    'purchase_account_id' => $category->purchase_account_id,
                ])
                ->sortBy('name')
                ->values(),
            'revenueAccounts' => $options->accounts(AccountSubtype::OperatingRevenue, AccountSubtype::OtherRevenue),
            'expenseAccounts' => $options->accounts(AccountSubtype::CostOfSales, AccountSubtype::OperatingExpense, AccountSubtype::OtherExpense),
            'inventoryAccounts' => $options->accounts(AccountSubtype::Inventory),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', ItemCategory::class);

        ItemCategory::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('admin.inventory.categories.index');
    }

    public function update(Request $request, ItemCategory $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('admin.inventory.categories.index');
    }

    public function destroy(ItemCategory $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        SafeDelete::delete($category);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('admin.inventory.categories.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $account = fn () => Rule::exists(Account::class, 'id')->where('is_group', false);

        $validated = $request->validate([
            ...$this->translationRules('name', 150),
            'inventory_account_id' => ['nullable', 'integer', $account()->where('subtype', AccountSubtype::Inventory->value)],
            'cost_of_sales_account_id' => ['nullable', 'integer', $account()->where('type', AccountType::Expense->value)],
            'sales_account_id' => ['nullable', 'integer', $account()->where('type', AccountType::Revenue->value)],
            'purchase_account_id' => ['nullable', 'integer', $account()->where('type', AccountType::Expense->value)],
        ]);

        return [...$validated, 'name' => array_filter($validated['name'])];
    }
}
