<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tax rates and the accounts their amounts are posted to.
 */
class TaxController extends Controller
{
    use ValidatesTranslations;

    public function index(AccountingOptions $options): Response
    {
        Gate::authorize('viewAny', Tax::class);

        return Inertia::render('admin/accounting/taxes/Index', [
            'taxes' => Tax::query()->with(['salesAccount', 'purchaseAccount'])->orderBy('rate')->get()->map(fn (Tax $tax): array => [
                'id' => $tax->id,
                'name' => $tax->translate('name'),
                'name_translations' => $tax->name,
                'rate' => (string) $tax->rate,
                'scope' => $tax->scope->value,
                'scope_label' => $tax->scope->label(),
                'sales_account_id' => $tax->sales_account_id,
                'sales_account' => $tax->salesAccount?->label(),
                'purchase_account_id' => $tax->purchase_account_id,
                'purchase_account' => $tax->purchaseAccount?->label(),
                'is_active' => $tax->is_active,
            ]),
            'scopes' => TaxScope::options(),
            'accounts' => $options->accounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Tax::class);

        Tax::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax created.')]);

        return to_route('admin.accounting.taxes.index');
    }

    public function update(Request $request, Tax $tax): RedirectResponse
    {
        Gate::authorize('update', $tax);

        $tax->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax updated.')]);

        return to_route('admin.accounting.taxes.index');
    }

    public function destroy(Tax $tax): RedirectResponse
    {
        Gate::authorize('delete', $tax);

        SafeDelete::delete($tax);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax deleted.')]);

        return to_route('admin.accounting.taxes.index');
    }

    /**
     * A tax with a rate needs the account of each side it applies to.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $postable = Rule::exists(Account::class, 'id')->where('is_group', false);
        $scope = TaxScope::tryFrom($request->string('scope')->value());
        $hasRate = (float) $request->input('rate') > 0;

        $validated = $request->validate([
            ...$this->translationRules('name', 100),
            'rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'scope' => ['required', Rule::enum(TaxScope::class)],
            'sales_account_id' => [Rule::requiredIf($hasRate && $scope?->appliesToSales()), 'nullable', 'integer', $postable],
            'purchase_account_id' => [Rule::requiredIf($hasRate && $scope?->appliesToPurchases()), 'nullable', 'integer', $postable],
            'is_active' => ['boolean'],
        ]);

        return [
            ...$validated,
            'name' => array_filter($validated['name']),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
