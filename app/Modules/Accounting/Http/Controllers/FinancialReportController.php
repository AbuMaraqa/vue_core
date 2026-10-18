<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Controllers\Concerns\RendersReports;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Reports\BalanceSheet;
use App\Modules\Accounting\Reports\BudgetVsActual;
use App\Modules\Accounting\Reports\CashFlowStatement;
use App\Modules\Accounting\Reports\FinancialOverview;
use App\Modules\Accounting\Reports\GeneralLedger;
use App\Modules\Accounting\Reports\IncomeStatement;
use App\Modules\Accounting\Reports\TaxSummary;
use App\Modules\Accounting\Reports\TrialBalance;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Foundation\Exports\Report;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The financial reports. Each report is one GET route: it renders the page,
 * or downloads the same report when `?format=xlsx|csv|pdf` is given.
 */
class FinancialReportController extends Controller
{
    use RendersReports;

    public function overview(FinancialOverview $overview): Response
    {
        Gate::authorize('Financial Report View');

        return $this->reportPage('admin/accounting/reports/Overview', ['overview' => $overview->generate()]);
    }

    public function trialBalance(Request $request, TrialBalance $report, AccountingOptions $options): Response
    {
        [$from, $to] = $this->reportPeriod($request);
        $costCenter = $request->integer('cost_center_id') ?: null;
        $includeZero = $request->boolean('include_zero');

        return $this->respond(
            $request,
            fn (): Report => $report->export($from, $to, $costCenter, $includeZero),
            'admin/accounting/reports/TrialBalance',
            [
                'report' => $report->generate($from, $to, $costCenter, $includeZero),
                'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'cost_center_id' => $costCenter, 'include_zero' => $includeZero],
                'costCenters' => $options->costCenters(),
            ],
        );
    }

    public function generalLedger(Request $request, GeneralLedger $report, AccountingOptions $options): Response
    {
        [$from, $to] = $this->reportPeriod($request);
        $account = Account::query()->find($request->integer('account_id'));
        $costCenter = $request->integer('cost_center_id') ?: null;

        return $this->respond(
            $request,
            fn (): Report => $report->export($account ?? abort(422), $from, $to, $costCenter),
            'admin/accounting/reports/GeneralLedger',
            [
                'report' => $account ? $report->generate($account, $from, $to, $costCenter) : null,
                'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'account_id' => $account?->id, 'cost_center_id' => $costCenter],
                'accounts' => $options->accounts(),
                'costCenters' => $options->costCenters(),
            ],
        );
    }

    public function balanceSheet(Request $request, BalanceSheet $report): Response
    {
        $asOf = $request->date('as_of') ?? now();

        return $this->respond(
            $request,
            fn (): Report => $report->export($asOf),
            'admin/accounting/reports/BalanceSheet',
            ['report' => $report->generate($asOf), 'filters' => ['as_of' => $asOf->toDateString()]],
        );
    }

    public function incomeStatement(Request $request, IncomeStatement $report, AccountingOptions $options): Response
    {
        [$from, $to] = $this->reportPeriod($request);
        $costCenter = $request->integer('cost_center_id') ?: null;
        $compare = $request->boolean('compare');
        $length = (int) $from->diffInDays($to) + 1;

        return $this->respond(
            $request,
            fn (): Report => $report->export($from, $to, $costCenter),
            'admin/accounting/reports/IncomeStatement',
            [
                'report' => $report->generate(
                    $from,
                    $to,
                    $costCenter,
                    $compare ? $from->copy()->subDays($length) : null,
                    $compare ? $from->copy()->subDay() : null,
                ),
                'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'cost_center_id' => $costCenter, 'compare' => $compare],
                'costCenters' => $options->costCenters(),
            ],
        );
    }

    public function cashFlow(Request $request, CashFlowStatement $report): Response
    {
        [$from, $to] = $this->reportPeriod($request);

        return $this->respond(
            $request,
            fn (): Report => $report->export($from, $to),
            'admin/accounting/reports/CashFlow',
            ['report' => $report->generate($from, $to), 'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]],
        );
    }

    public function taxSummary(Request $request, TaxSummary $report): Response
    {
        [$from, $to] = $this->reportPeriod($request, defaultFromStartOfMonth: true);

        return $this->respond(
            $request,
            fn (): Report => $report->export($from, $to),
            'admin/accounting/reports/TaxSummary',
            ['report' => $report->generate($from, $to), 'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]],
        );
    }

    public function budgetVsActual(Request $request, BudgetVsActual $report): Response
    {
        $budget = Budget::query()->find($request->integer('budget_id'));
        $month = max(1, min(12, $request->integer('month', 12)));

        return $this->respond(
            $request,
            fn (): Report => $report->export($budget ?? abort(422), $month),
            'admin/accounting/reports/BudgetVsActual',
            [
                'report' => $budget ? $report->generate($budget, $month) : null,
                'filters' => ['budget_id' => $budget?->id, 'month' => $month],
                'budgets' => Budget::query()->with('fiscalYear')->latest('id')->get()
                    ->map(fn (Budget $item): array => ['value' => $item->id, 'label' => "{$item->name} ({$item->fiscalYear->name})"])
                    ->values(),
            ],
        );
    }

    /**
     * Render the page, or download the report when a format is requested.
     *
     * @param  Closure(): Report  $export
     * @param  array<string, mixed>  $props
     */
    protected function respond(Request $request, Closure $export, string $component, array $props): Response
    {
        Gate::authorize('Financial Report View');

        return $this->report($request, $component, $props, $export);
    }
}
