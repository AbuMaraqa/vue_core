<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Http\Requests\Accounts\SaveAccountRequest;
use App\Modules\Accounting\Http\Resources\AccountResource;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\AccountTree;
use App\Modules\Accounting\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The chart of accounts: a tree with the current balance of every account.
 */
class AccountController extends Controller
{
    public function index(Request $request, AccountTree $tree, AccountBalances $balances, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', Account::class);

        $accounts = Account::query()->withExists(['journalLines', 'children'])->get();
        $totals = $balances->rollUp($accounts, $balances->asOf(now()));

        return Inertia::render('admin/accounting/accounts/Index', [
            'accounts' => array_map(fn (array $row): array => [
                ...AccountResource::make($row['account'])->resolve($request),
                'depth' => $row['depth'],
                'has_children' => $row['has_children'],
                'balance' => $totals[$row['account']->id] ?? '0',
            ], $tree->flatten($accounts)),
            'parents' => $tree->groupOptions(),
            'subtypes' => array_map(fn (AccountSubtype $subtype): array => [
                'value' => $subtype->value,
                'label' => $subtype->label(),
                'type' => $subtype->type()->value,
            ], AccountSubtype::cases()),
            'types' => AccountType::options(),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get()
                ->map(fn (Currency $currency): array => ['value' => $currency->id, 'label' => $currency->code])
                ->values(),
        ]);
    }

    public function store(SaveAccountRequest $request): RedirectResponse
    {
        Account::query()->create($this->attributes($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created.')]);

        return to_route('admin.accounting.accounts.index');
    }

    public function update(SaveAccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($this->attributes($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return to_route('admin.accounting.accounts.index');
    }

    public function destroy(Account $account): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $account->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account deleted.')]);

        return to_route('admin.accounting.accounts.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(SaveAccountRequest $request): array
    {
        $subtype = AccountSubtype::from($request->string('subtype')->value());

        return [
            'parent_id' => $request->integer('parent_id') ?: null,
            'code' => $request->string('code')->value(),
            'name' => array_filter((array) $request->validated('name')),
            'type' => $subtype->type(),
            'subtype' => $subtype,
            'is_group' => $request->boolean('is_group'),
            'is_active' => $request->boolean('is_active', true),
            'currency_id' => $request->integer('currency_id') ?: null,
            'description' => $request->validated('description'),
        ];
    }
}
