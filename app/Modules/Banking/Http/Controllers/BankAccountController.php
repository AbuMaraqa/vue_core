<?php

namespace App\Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Http\Controllers\Concerns\RendersReports;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Banking\Actions\BankAccounts\SaveBankAccount;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Enums\ChequeStatus;
use App\Modules\Banking\Http\Requests\SaveBankAccountRequest;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankReconciliation;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Banking\Models\ChequeBook;
use App\Modules\Banking\Printing\BankAccountStatementDocument;
use App\Modules\Banking\Reports\BankAccountRegister;
use App\Modules\Banking\Support\BankBalances;
use App\Modules\Foundation\Exports\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Cash boxes and bank accounts: the treasury overview, and the register of
 * each account.
 */
class BankAccountController extends Controller
{
    use RendersReports;

    public function index(Request $request, BankBalances $balances, Currencies $currencies, AccountingOptions $options): Response
    {
        Gate::authorize('viewAny', BankAccount::class);

        $bankAccounts = BankAccount::query()
            ->with(['currency', 'account' => fn ($query) => $query->withExists('journalLines')])
            ->orderBy('type')
            ->orderBy('name')
            ->get();
        $accountBalances = $balances->of($bankAccounts);

        return Inertia::render('admin/banking/accounts/Index', [
            'bankAccounts' => $bankAccounts->map(fn (BankAccount $bankAccount): array => [
                'id' => $bankAccount->id,
                'name' => $bankAccount->name,
                'type' => $bankAccount->type->value,
                'type_label' => $bankAccount->type->label(),
                'account' => $bankAccount->account->label(),
                'currency_id' => $bankAccount->currency_id,
                'currency' => ['code' => $bankAccount->currency->code, 'decimal_places' => $bankAccount->currency->decimal_places],
                'bank_name' => $bankAccount->bank_name,
                'branch' => $bankAccount->branch,
                'account_number' => $bankAccount->account_number,
                'iban' => $bankAccount->iban,
                'is_active' => $bankAccount->is_active,
                'balance' => $accountBalances[$bankAccount->id]['balance'],
                'base_balance' => $accountBalances[$bankAccount->id]['base_balance'],
                'can' => [
                    'update' => $request->user()?->can('update', $bankAccount) ?? false,
                    'delete' => $request->user()?->can('delete', $bankAccount) ?? false,
                ],
            ]),
            'totals' => [
                'cash' => Decimal::round(Decimal::sum($bankAccounts->filter(fn (BankAccount $bankAccount): bool => $bankAccount->type === BankAccountType::Cash)->map(fn (BankAccount $bankAccount): string => $accountBalances[$bankAccount->id]['base_balance']))),
                'bank' => Decimal::round(Decimal::sum($bankAccounts->filter(fn (BankAccount $bankAccount): bool => $bankAccount->type === BankAccountType::Bank)->map(fn (BankAccount $bankAccount): string => $accountBalances[$bankAccount->id]['base_balance']))),
                'cheques_receivable' => $this->chequesTotal(ChequeDirection::Incoming),
                'cheques_payable' => $this->chequesTotal(ChequeDirection::Outgoing),
            ],
            'types' => BankAccountType::options(),
            'currencies' => $options->currencies(),
            'linkableAccounts' => array_values(array_filter(
                $options->accounts(AccountSubtype::Cash, AccountSubtype::Bank),
                fn (array $account): bool => ! $bankAccounts->contains('account_id', $account['value']),
            )),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function store(SaveBankAccountRequest $request, SaveBankAccount $saveBankAccount): RedirectResponse
    {
        $saveBankAccount->handle(new BankAccount, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bank account created.')]);

        return to_route('admin.banking.accounts.index');
    }

    /**
     * The account's register for a period; `?format=` downloads it.
     */
    public function show(Request $request, BankAccount $bankAccount, BankAccountRegister $register, BankBalances $balances): SymfonyResponse
    {
        Gate::authorize('view', $bankAccount);

        [$from, $to] = $this->reportPeriod($request);
        $bankAccount->load(['currency', 'account']);

        return $this->report(
            $request,
            'admin/banking/accounts/Show',
            [
                'bankAccount' => [
                    'id' => $bankAccount->id,
                    'name' => $bankAccount->name,
                    'type_label' => $bankAccount->type->label(),
                    'account' => $bankAccount->account->label(),
                    'account_id' => $bankAccount->account_id,
                    'currency' => ['code' => $bankAccount->currency->code, 'decimal_places' => $bankAccount->currency->decimal_places],
                    'bank_name' => $bankAccount->bank_name,
                    'branch' => $bankAccount->branch,
                    'account_number' => $bankAccount->account_number,
                    'iban' => $bankAccount->iban,
                    'is_active' => $bankAccount->is_active,
                    'balance' => $balances->balance($bankAccount),
                ],
                'register' => $register->generate($bankAccount, $from, $to),
                'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'chequeBooks' => $bankAccount->chequeBooks()->orderByDesc('id')->get()->map(fn (ChequeBook $book): array => [
                    'id' => $book->id,
                    'first_number' => $book->first_number,
                    'last_number' => $book->last_number,
                    'next_number' => $book->next_number,
                    'remaining' => $book->remaining(),
                    'is_active' => $book->is_active,
                ]),
                'reconciliations' => $bankAccount->reconciliations()->with('creator')->latest('id')->limit(12)->get()
                    ->map(fn (BankReconciliation $reconciliation): array => [
                        'id' => $reconciliation->id,
                        'statement_date' => $reconciliation->statement_date->toDateString(),
                        'statement_balance' => (string) $reconciliation->statement_balance,
                        'created_by' => $reconciliation->creator?->name,
                    ]),
                'can' => [
                    'update' => $request->user()?->can('update', $bankAccount) ?? false,
                    'reconcile' => $request->user()?->can('reconcile', $bankAccount) ?? false,
                ],
            ],
            fn (): Report => $register->export($bankAccount, $from, $to),
            exportPermission: 'Bank Account View',
        );
    }

    /**
     * The printable statement of the account for the period of its register page.
     */
    public function pdf(Request $request, BankAccount $bankAccount, BankAccountRegister $register): BankAccountStatementDocument
    {
        Gate::authorize('view', $bankAccount);

        [$from, $to] = $this->reportPeriod($request);

        return new BankAccountStatementDocument($bankAccount, $register->generate($bankAccount, $from, $to), $from, $to);
    }

    public function update(SaveBankAccountRequest $request, BankAccount $bankAccount, SaveBankAccount $saveBankAccount): RedirectResponse
    {
        $saveBankAccount->handle($bankAccount, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bank account updated.')]);

        return back();
    }

    /**
     * Remove an unused bank account and its account in the chart.
     */
    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        Gate::authorize('delete', $bankAccount);

        DB::transaction(function () use ($bankAccount): void {
            $account = $bankAccount->account;

            SafeDelete::delete($bankAccount);
            SafeDelete::delete($account);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bank account deleted.')]);

        return to_route('admin.banking.accounts.index');
    }

    /**
     * The base value of the open cheques of a direction.
     */
    protected function chequesTotal(ChequeDirection $direction): string
    {
        $total = Cheque::query()
            ->where('direction', $direction)
            ->whereIn('status', ChequeStatus::open())
            ->get(['amount', 'exchange_rate'])
            ->map(fn (Cheque $cheque): string => Decimal::multiply($cheque->amount, $cheque->exchange_rate));

        return Decimal::round(Decimal::sum($total));
    }
}
