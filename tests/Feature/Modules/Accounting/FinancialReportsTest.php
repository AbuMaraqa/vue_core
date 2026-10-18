<?php

use App\Modules\Accounting\Actions\FiscalYears\CloseFiscalYear;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\BudgetLine;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Reports\BalanceSheet;
use App\Modules\Accounting\Reports\BudgetVsActual;
use App\Modules\Accounting\Reports\CashFlowStatement;
use App\Modules\Accounting\Reports\GeneralLedger;
use App\Modules\Accounting\Reports\IncomeStatement;
use App\Modules\Accounting\Reports\TaxSummary;
use App\Modules\Accounting\Reports\TrialBalance;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A small but complete year of business, with figures easy to check by hand:
 *
 *   capital 10,000 · sale 1,000 + 160 VAT (collected) · rent 500
 *   computer 2,000 · depreciation 100 · loan 3,000
 *
 * Cash: 10,000 + 1,160 − 500 − 2,000 + 3,000 = 11,660. Net profit: 1,000 − 600 = 400.
 */
beforeEach(function () {
    seedAccounting();

    $post = fn (string $description, array $debits, array $credits) => tap(
        JournalEntryBuilder::make(now(), $description),
        function ($builder) use ($debits, $credits): void {
            foreach ($debits as $code => $amount) {
                $builder->debit(account($code), $amount);
            }

            foreach ($credits as $code => $amount) {
                $builder->credit(account($code), $amount);
            }
        },
    )->post();

    $post('Capital', ['110101' => 10000], ['3101' => 10000]);
    $post('Sale', ['1103' => 1160], ['4101' => 1000, '2103' => 160]);
    $post('Collection', ['110101' => 1160], ['1103' => 1160]);
    $post('Rent', ['5203' => 500], ['110101' => 500]);
    $post('Computer', ['1202' => 2000], ['110101' => 2000]);
    $post('Depreciation', ['5205' => 100], ['120902' => 100]);
    $post('Loan', ['110101' => 3000], ['2201' => 3000]);

    $this->from = now()->startOfYear();
    $this->to = now()->endOfYear();
});

test('the trial balance balances', function () {
    $report = app(TrialBalance::class)->generate($this->from, $this->to);

    expect($report['balanced'])->toBeTrue()
        ->and($report['totals']['debit'])->toBe($report['totals']['credit'])
        ->and(collect($report['rows'])->firstWhere('code', '110101')['closing_debit'])->toBe('11660.0000');
});

test('the income statement shows the profit of the period', function () {
    $report = app(IncomeStatement::class)->generate($this->from, $this->to);

    expect(collect($report['sections'])->firstWhere('key', 'revenue')['total'])->toBe('1000.0000')
        ->and(collect($report['sections'])->firstWhere('key', 'operating_expenses')['total'])->toBe('600.0000')
        ->and($report['gross_profit'])->toBe('1000.0000')
        ->and($report['net_profit'])->toBe('400.0000');
});

test('the balance sheet balances with the unclosed profit in equity', function () {
    $report = app(BalanceSheet::class)->generate($this->to);

    expect($report['balanced'])->toBeTrue()
        ->and($report['total_assets'])->toBe('13560.0000')
        ->and($report['current_earnings'])->toBe('400.0000');
});

test('the balance sheet still balances after the year is closed', function () {
    app(CloseFiscalYear::class)->handle(FiscalYear::query()->sole());

    $report = app(BalanceSheet::class)->generate($this->to);

    expect($report['balanced'])->toBeTrue()
        ->and($report['current_earnings'])->toBe('0.0000');
});

test('the income statement of a closed year still shows its activity', function () {
    app(CloseFiscalYear::class)->handle(FiscalYear::query()->sole());

    expect(app(IncomeStatement::class)->generate($this->from, $this->to)['net_profit'])->toBe('400.0000');
});

test('the cash flow statement explains the change in cash', function () {
    $report = app(CashFlowStatement::class)->generate($this->from, $this->to);
    $section = fn (string $key) => collect($report['sections'])->firstWhere('key', $key)['total'];

    expect($report['reconciled'])->toBeTrue()
        ->and($section('operating'))->toBe('660.0000')
        ->and($section('investing'))->toBe('-2000.0000')
        ->and($section('financing'))->toBe('13000.0000')
        ->and($report['net_change'])->toBe('11660.0000')
        ->and($report['closing_cash'])->toBe('11660.0000');
});

test('the tax report shows the tax owed', function () {
    $report = app(TaxSummary::class)->generate($this->from, $this->to);

    expect($report['total_output'])->toBe('160.0000')
        ->and($report['net'])->toBe('160.0000');
});

test('the general ledger shows a running balance', function () {
    $report = app(GeneralLedger::class)->generate(account('110101'), $this->from, $this->to);

    expect($report['lines'])->toHaveCount(5)
        ->and($report['lines'][0]['balance'])->toBe('10000.0000')
        ->and($report['lines'][2]['balance'])->toBe('10660.0000')
        ->and($report['closing'])->toBe('11660.0000');
});

test('budgets are compared with the actual amounts', function () {
    $budget = Budget::factory()->create(['fiscal_year_id' => FiscalYear::query()->sole()->id]);

    foreach (range(1, 12) as $month) {
        BudgetLine::query()->create(['budget_id' => $budget->id, 'account_id' => account('5203')->id, 'month' => $month, 'amount' => '40']);
    }

    $report = app(BudgetVsActual::class)->generate($budget);
    $rent = collect($report['rows'])->firstWhere('account_id', account('5203')->id);

    expect($rent)->budget->toBe('480.0000')->actual->toBe('500.0000')->variance->toBe('20.0000');
});

describe('pages and downloads', function () {
    test('reports need their permission', function () {
        $this->actingAs(staffMember())->get(route('admin.accounting.reports.trial-balance'))->assertForbidden();
    });

    test('every report page renders', function (string $route, string $component) {
        $this->actingAs(staffMember('Financial Report View'))
            ->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->has('baseCurrency'));
    })->with([
        ['admin.accounting.reports.overview', 'admin/accounting/reports/Overview'],
        ['admin.accounting.reports.trial-balance', 'admin/accounting/reports/TrialBalance'],
        ['admin.accounting.reports.general-ledger', 'admin/accounting/reports/GeneralLedger'],
        ['admin.accounting.reports.balance-sheet', 'admin/accounting/reports/BalanceSheet'],
        ['admin.accounting.reports.income-statement', 'admin/accounting/reports/IncomeStatement'],
        ['admin.accounting.reports.cash-flow', 'admin/accounting/reports/CashFlow'],
        ['admin.accounting.reports.tax-summary', 'admin/accounting/reports/TaxSummary'],
        ['admin.accounting.reports.budget-vs-actual', 'admin/accounting/reports/BudgetVsActual'],
    ]);

    test('downloading a report needs the export permission', function () {
        $this->actingAs(staffMember('Financial Report View'))
            ->get(route('admin.accounting.reports.trial-balance', ['format' => 'csv']))
            ->assertForbidden();

        $this->actingAs(staffMember('Financial Report View', 'Financial Report Export'))
            ->get(route('admin.accounting.reports.trial-balance', ['format' => 'csv']))
            ->assertOk()
            ->assertDownload();
    });

    test('a report downloads as a pdf in arabic', function () {
        $response = $this->actingAs(staffMember('Financial Report View', 'Financial Report Export'))
            ->get('/ar/admin/accounting/reports/balance-sheet?format=pdf')
            ->assertOk();

        expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
    });
});

describe('dashboard figures', function () {
    test('the dashboard shows the key figures of the business', function () {
        $this->actingAs(staffMember('Financial Report View'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('businessStats')
                ->loadDeferredProps('business', fn (Assert $reload) => $reload
                    ->has('businessStats', 4)
                    ->where('businessStats.0.key', 'money-in-cash-and-banks')
                    ->where('businessStats.0.value', '11660.0000')
                    ->where('businessStats.0.currency', 'JOD')
                    ->where('businessStats.1.key', 'customers-owe-you')
                    ->where('businessStats.1.value', '0.0000')
                    ->where('businessStats.3.key', 'profit-this-month')
                    ->where('businessStats.3.value', '400.0000')
                    ->where('businessStats.3.url', route('admin.accounting.reports.income-statement', absolute: false)),
                ),
            );
    });

    test('the figures are hidden from staff members who may not see the reports', function () {
        $this->actingAs(staffMember())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps('business', fn (Assert $reload) => $reload->has('businessStats', 0)),
            );
    });
});
