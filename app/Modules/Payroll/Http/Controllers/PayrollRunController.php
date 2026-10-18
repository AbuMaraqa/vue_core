<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Support\BankingOptions;
use App\Modules\Payroll\Actions\PayPayrollRun;
use App\Modules\Payroll\Actions\PostPayrollRun;
use App\Modules\Payroll\Actions\PreparePayrollRun;
use App\Modules\Payroll\Actions\ReversePayrollRun;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayComponent;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayslipLine;
use App\Modules\Payroll\Printing\PayrollRunDocument;
use App\Modules\Payroll\Printing\PayslipDocument;
use App\Modules\Payroll\Support\PayslipCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monthly payroll: prepare a draft, adjust payslips, post the accrual, pay
 * the net salaries, or step back.
 */
class PayrollRunController extends Controller
{
    public function index(Request $request, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', PayrollRun::class);

        return Inertia::render('admin/payroll/runs/Index', [
            'runs' => PayrollRun::query()->withCount('payslips')->latest('period_start')->latest('id')->paginate(12)
                ->through(fn (PayrollRun $run): array => $this->summary($run)),
            'nextMonth' => Carbon::parse(PayrollRun::query()->where('status', '!=', PayrollRunStatus::Cancelled)->max('period_start') ?? now()->subMonth())->addMonth()->format('Y-m'),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function store(Request $request, PreparePayrollRun $prepare): RedirectResponse
    {
        Gate::authorize('create', PayrollRun::class);

        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $run = $prepare->handle(Carbon::parse($validated['month'].'-01'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll prepared. Review the payslips, then post it.')]);

        return to_route('admin.payroll.runs.show', $run);
    }

    public function show(Request $request, PayrollRun $run, BankingOptions $banking, Currencies $currencies): Response
    {
        Gate::authorize('view', $run);

        $run->load(['payslips.employee', 'payslips.lines.component', 'payslips.costCenter', 'bankAccount', 'journalEntries', 'creator']);

        return Inertia::render('admin/payroll/runs/Show', [
            'run' => [
                ...$this->summary($run),
                'bank_account' => $run->bankAccount?->label(),
                'created_by' => $run->creator?->name,
                'journal_entries' => $run->journalEntries->map(fn ($entry): array => ['id' => $entry->id, 'number' => $entry->number, 'description' => $entry->description])->values(),
                'payslips' => $run->payslips->sortBy(fn (Payslip $payslip): string => $payslip->employee->code)->map(fn (Payslip $payslip): array => [
                    'id' => $payslip->id,
                    'employee_id' => $payslip->employee_id,
                    'employee' => $payslip->employee->label(),
                    'cost_center' => $payslip->costCenter?->label(),
                    'days_worked' => $payslip->days_worked,
                    'days_in_period' => $payslip->days_in_period,
                    'basic' => (string) $payslip->basic,
                    'earnings_total' => (string) $payslip->earnings_total,
                    'deductions_total' => (string) $payslip->deductions_total,
                    'employer_total' => (string) $payslip->employer_total,
                    'net_pay' => (string) $payslip->net_pay,
                    'lines' => $payslip->lines->map(fn (PayslipLine $line): array => [
                        'pay_component_id' => $line->pay_component_id,
                        'name' => $line->component->translate('name'),
                        'type' => $line->type->value,
                        'amount' => (string) $line->amount,
                    ])->values(),
                ])->values(),
                'can' => [
                    'update' => $run->status === PayrollRunStatus::Draft && ($request->user()?->can('update', $run) ?? false),
                    'post' => $request->user()?->can('post', $run) ?? false,
                    'pay' => $request->user()?->can('pay', $run) ?? false,
                    'post_and_pay' => $request->user()?->can('postAndPay', $run) ?? false,
                    'reverse' => $request->user()?->can('reverse', $run) ?? false,
                ],
            ],
            'payComponents' => PayComponent::query()->orderByDesc('is_active')->get()->map(fn (PayComponent $component): array => [
                'value' => $component->id,
                'label' => $component->translate('name'),
                'type' => $component->type->value,
            ])->values(),
            'bankAccounts' => array_values(array_filter(
                $banking->bankAccounts(),
                fn (array $account): bool => $currencies->isBase($account['currency_id']),
            )),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    /**
     * The printable summary of the run: every payslip with the totals.
     */
    public function pdf(PayrollRun $run): PayrollRunDocument
    {
        Gate::authorize('view', $run);

        return new PayrollRunDocument($run);
    }

    /**
     * The printable payslip of one employee of the run.
     */
    public function payslipPdf(PayrollRun $run, Payslip $payslip): PayslipDocument
    {
        Gate::authorize('view', $run);
        abort_unless($payslip->payroll_run_id === $run->id, 404);

        return new PayslipDocument($payslip->setRelation('run', $run));
    }

    /**
     * Prepare a draft again from the employees' current salaries; manual
     * changes to its payslips are lost.
     */
    public function prepare(PayrollRun $run, PreparePayrollRun $prepare): RedirectResponse
    {
        Gate::authorize('update', $run);

        $prepare->handle($run->period_start, $run);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll prepared again.')]);

        return back();
    }

    /**
     * Adjust one payslip of a draft: its basic and its components.
     */
    public function updatePayslip(Request $request, PayrollRun $run, Payslip $payslip): RedirectResponse
    {
        Gate::authorize('update', $run);
        abort_unless($payslip->payroll_run_id === $run->id && $run->status === PayrollRunStatus::Draft, 404);

        $validated = $request->validate([
            'basic' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'lines' => ['array', 'max:50'],
            'lines.*.pay_component_id' => ['required', 'integer', 'distinct', Rule::exists(PayComponent::class, 'id')],
            'lines.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
        ]);

        DB::transaction(function () use ($validated, $payslip, $run): void {
            $components = PayComponent::query()->findMany(array_column($validated['lines'] ?? [], 'pay_component_id'))->keyBy('id');
            $lines = array_map(fn (array $line): array => [
                'pay_component_id' => (int) $line['pay_component_id'],
                'type' => $components[$line['pay_component_id']]->type,
                'amount' => Decimal::round((string) $line['amount']),
            ], $validated['lines'] ?? []);

            $basic = Decimal::round((string) $validated['basic']);

            $payslip->lines()->delete();
            $payslip->lines()->createMany($lines);
            $payslip->update(['basic' => $basic, ...PayslipCalculator::totals($basic, $lines)]);

            PreparePayrollRun::refreshTotals($run);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payslip updated.')]);

        return back();
    }

    public function post(PayrollRun $run, PostPayrollRun $postRun): RedirectResponse
    {
        Gate::authorize('post', $run);

        $postRun->handle($run);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll posted.')]);

        return back();
    }

    public function pay(Request $request, PayrollRun $run, PayPayrollRun $payRun): RedirectResponse
    {
        Gate::authorize('pay', $run);

        $validated = $request->validate($this->paymentRules($run));

        $payRun->handle($run, BankAccount::query()->whereKey($validated['bank_account_id'])->firstOrFail(), $validated['date']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Salaries paid.')]);

        return back();
    }

    /**
     * Simple mode's single step: post the payroll if it is still a draft,
     * then pay it, all or nothing.
     */
    public function postAndPay(Request $request, PayrollRun $run, PostPayrollRun $postRun, PayPayrollRun $payRun): RedirectResponse
    {
        Gate::authorize('postAndPay', $run);

        $validated = $request->validate($this->paymentRules($run));
        $bankAccount = BankAccount::query()->whereKey($validated['bank_account_id'])->firstOrFail();

        DB::transaction(function () use ($run, $bankAccount, $validated, $postRun, $payRun): void {
            if ($run->status === PayrollRunStatus::Draft) {
                $run = $postRun->handle($run);
            }

            $payRun->handle($run, $bankAccount, $validated['date']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Salaries paid.')]);

        return back();
    }

    /**
     * Step back: delete a draft, cancel a posted payroll, or reverse the
     * payment of a paid one.
     */
    public function reverse(PayrollRun $run, ReversePayrollRun $reverseRun): RedirectResponse
    {
        Gate::authorize('reverse', $run);

        $result = $reverseRun->handle($run);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll updated.')]);

        return $result === null ? to_route('admin.payroll.runs.index') : back();
    }

    /**
     * The account and date a payroll is paid with.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function paymentRules(PayrollRun $run): array
    {
        return [
            'bank_account_id' => ['required', 'integer', Rule::exists(BankAccount::class, 'id')->where('is_active', true)],
            'date' => ['required', 'date', 'after_or_equal:'.$run->period_start->toDateString()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(PayrollRun $run): array
    {
        return [
            'id' => $run->id,
            'number' => $run->number,
            'period' => $run->period_start->format('Y-m'),
            'period_start' => $run->period_start->toDateString(),
            'period_end' => $run->period_end->toDateString(),
            'payment_date' => $run->payment_date?->toDateString(),
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'status_color' => $run->status->color(),
            'employees' => $run->getAttribute('payslips_count') ?? $run->payslips->count(),
            'gross_total' => (string) $run->gross_total,
            'deductions_total' => (string) $run->deductions_total,
            'employer_total' => (string) $run->employer_total,
            'net_total' => (string) $run->net_total,
        ];
    }
}
