<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\BudgetLine;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Budgets: the planned revenue and expenses of a fiscal year, month by month,
 * for the whole company or one cost center.
 */
class BudgetController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Budget::class);

        return Inertia::render('admin/accounting/budgets/Index', [
            'budgets' => Budget::query()->with(['fiscalYear', 'costCenter'])->withSum('lines', 'amount')->latest('id')->get()
                ->map(fn (Budget $budget): array => [
                    'id' => $budget->id,
                    'name' => $budget->name,
                    'fiscal_year' => $budget->fiscalYear->name,
                    'cost_center' => $budget->costCenter?->label(),
                    'total' => Decimal::round((string) ($budget->getAttribute('lines_sum_amount') ?? '0')),
                ]),
        ]);
    }

    public function create(AccountingOptions $options): Response
    {
        Gate::authorize('create', Budget::class);

        return Inertia::render('admin/accounting/budgets/Edit', $this->formProps($options));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Budget::class);

        $this->save(new Budget, $this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Budget saved.')]);

        return to_route('admin.accounting.budgets.index');
    }

    public function edit(Budget $budget, AccountingOptions $options): Response
    {
        Gate::authorize('update', $budget);

        $lines = $budget->lines()->get()->groupBy('account_id')->map(fn ($months, $accountId): array => [
            'account_id' => (int) $accountId,
            'months' => array_map(
                fn (int $month): string => (string) ($months->firstWhere('month', $month)->amount ?? '0'),
                range(1, 12),
            ),
        ])->values();

        return Inertia::render('admin/accounting/budgets/Edit', [
            ...$this->formProps($options),
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'fiscal_year_id' => $budget->fiscal_year_id,
                'cost_center_id' => $budget->cost_center_id,
                'notes' => $budget->notes,
                'lines' => $lines,
            ],
        ]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        Gate::authorize('update', $budget);

        $this->save($budget, $this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Budget saved.')]);

        return to_route('admin.accounting.budgets.index');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        Gate::authorize('delete', $budget);

        $budget->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Budget deleted.')]);

        return to_route('admin.accounting.budgets.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fiscal_year_id' => ['required', 'integer', Rule::exists(FiscalYear::class, 'id')],
            'cost_center_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['present', 'array', 'max:500'],
            'lines.*.account_id' => ['required', 'integer', 'distinct', Rule::exists(Account::class, 'id')
                ->where('is_group', false)
                ->whereIn('type', [AccountType::Revenue->value, AccountType::Expense->value])],
            'lines.*.months' => ['required', 'array', 'size:12'],
            'lines.*.months.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function save(Budget $budget, array $data): void
    {
        DB::transaction(function () use ($budget, $data): void {
            $budget->fill([
                'name' => $data['name'],
                'fiscal_year_id' => $data['fiscal_year_id'],
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $budget->lines()->delete();

            foreach ($data['lines'] as $line) {
                foreach (array_values($line['months']) as $index => $amount) {
                    if (Decimal::isPositive($amount)) {
                        BudgetLine::query()->create([
                            'budget_id' => $budget->id,
                            'account_id' => $line['account_id'],
                            'month' => $index + 1,
                            'amount' => Decimal::round($amount),
                        ]);
                    }
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function formProps(AccountingOptions $options): array
    {
        return [
            'accounts' => Account::query()
                ->where('is_group', false)
                ->whereIn('type', [AccountType::Revenue, AccountType::Expense])
                ->orderBy('code')
                ->get()
                ->map(fn (Account $account): array => ['value' => $account->id, 'label' => $account->label(), 'hint' => $account->type->label()])
                ->values(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('start_date')->get()
                ->map(fn (FiscalYear $year): array => ['value' => $year->id, 'label' => $year->name])
                ->values(),
            'costCenters' => $options->costCenters(),
        ];
    }
}
