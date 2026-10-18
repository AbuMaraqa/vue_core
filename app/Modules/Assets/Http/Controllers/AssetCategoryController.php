<?php

namespace App\Modules\Assets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Assets\Enums\DepreciationMethod;
use App\Modules\Assets\Models\AssetCategory;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Asset categories with their accounts and default depreciation.
 */
class AssetCategoryController extends Controller
{
    use ValidatesTranslations;

    public function index(AccountingOptions $options, DefaultAccounts $defaults): Response
    {
        Gate::authorize('viewAny', AssetCategory::class);

        return Inertia::render('admin/assets/categories/Index', [
            'categories' => AssetCategory::query()->with(['assetAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount'])->withCount('assets')->get()
                ->map(fn (AssetCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->translate('name'),
                    'name_translations' => $category->name,
                    'asset_account_id' => $category->asset_account_id,
                    'asset_account' => $category->assetAccount->label(),
                    'accumulated_depreciation_account_id' => $category->accumulated_depreciation_account_id,
                    'accumulated_depreciation_account' => $category->accumulatedDepreciationAccount->label(),
                    'depreciation_expense_account_id' => $category->depreciation_expense_account_id,
                    'depreciation_expense_account' => $category->depreciationExpenseAccount->label(),
                    'method' => $category->method->value,
                    'method_label' => $category->method->label(),
                    'useful_life_months' => $category->useful_life_months,
                    'assets_count' => $category->getAttribute('assets_count'),
                ])
                ->sortBy('name')
                ->values(),
            'methods' => DepreciationMethod::options(),
            'assetAccounts' => $options->accounts(AccountSubtype::FixedAsset, AccountSubtype::OtherAsset),
            'accumulatedAccounts' => $options->accounts(AccountSubtype::AccumulatedDepreciation),
            'expenseAccounts' => $options->accounts(AccountSubtype::OperatingExpense, AccountSubtype::CostOfSales, AccountSubtype::OtherExpense),
            'defaults' => ['depreciation_expense_account_id' => $defaults->id(AccountRole::DepreciationExpense)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', AssetCategory::class);

        AssetCategory::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('admin.assets.categories.index');
    }

    public function update(Request $request, AssetCategory $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('admin.assets.categories.index');
    }

    public function destroy(AssetCategory $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        SafeDelete::delete($category);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('admin.assets.categories.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $account = fn () => Rule::exists(Account::class, 'id')->where('is_group', false);

        $validated = $request->validate([
            ...$this->translationRules('name', 100),
            'asset_account_id' => ['required', 'integer', $account()->where('type', AccountType::Asset->value)],
            'accumulated_depreciation_account_id' => ['required', 'integer', 'different:asset_account_id', $account()->where('subtype', AccountSubtype::AccumulatedDepreciation->value)],
            'depreciation_expense_account_id' => ['required', 'integer', $account()->where('type', AccountType::Expense->value)],
            'method' => ['required', Rule::enum(DepreciationMethod::class)],
            'useful_life_months' => ['required', 'integer', 'between:1,1200'],
        ]);

        return [...$validated, 'name' => array_filter($validated['name'])];
    }
}
