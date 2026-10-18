<?php

namespace App\Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Banking\Actions\Reconciliations\ReconcileBankAccount;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\BankReconciliation;
use App\Modules\Banking\Support\BankBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bank reconciliation: tick the lines that appear on the bank statement
 * until the cleared balance equals the statement's closing balance.
 */
class ReconciliationController extends Controller
{
    /**
     * Open lines shown at once; older ones are reconciled first.
     */
    public const int MAX_LINES = 2000;

    public function create(Request $request, BankAccount $bankAccount, BankBalances $balances, ReconcileBankAccount $reconcile): Response
    {
        Gate::authorize('reconcile', $bankAccount);

        $statementDate = ($request->date('statement_date') ?? now())->toDateString();
        $bankAccount->load('currency');
        $last = $bankAccount->reconciliations()->latest('id')->first();

        $open = $balances->lines($bankAccount, null, $statementDate)
            ->whereNull('journal_lines.reconciliation_id')
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->limit(self::MAX_LINES + 1)
            ->get();

        return Inertia::render('admin/banking/reconciliations/Create', [
            'bankAccount' => [
                'id' => $bankAccount->id,
                'label' => $bankAccount->label(),
                'currency' => ['code' => $bankAccount->currency->code, 'decimal_places' => $bankAccount->currency->decimal_places],
                'book_balance' => $balances->balance($bankAccount, $statementDate),
            ],
            'statementDate' => $statementDate,
            'reconciledBalance' => $reconcile->reconciledBalance($bankAccount),
            'lastReconciliation' => $last ? [
                'statement_date' => $last->statement_date->toDateString(),
                'statement_balance' => (string) $last->statement_balance,
            ] : null,
            'lines' => $open
                ->take(self::MAX_LINES)
                ->map(fn (object $line): array => [
                    'id' => (int) $line->id,
                    'entry_id' => (int) $line->entry_id,
                    'date' => substr((string) $line->date, 0, 10),
                    'number' => $line->number,
                    'reference' => $line->reference,
                    'description' => $line->description ?? $line->entry_description,
                    'amount' => (string) $line->amount,
                ])
                ->values(),
            'truncated' => $open->count() > self::MAX_LINES,
        ]);
    }

    public function store(Request $request, BankAccount $bankAccount, ReconcileBankAccount $reconcile): RedirectResponse
    {
        Gate::authorize('reconcile', $bankAccount);

        $validated = $request->validate([
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric', 'decimal:0,4'],
            'line_ids' => ['present', 'array'],
            'line_ids.*' => ['integer'],
        ]);

        $reconcile->handle(
            $bankAccount,
            $validated['statement_date'],
            (string) $validated['statement_balance'],
            array_values(array_map('intval', $validated['line_ids'])),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The bank statement is reconciled.')]);

        return to_route('admin.banking.accounts.show', $bankAccount);
    }

    public function destroy(BankAccount $bankAccount, BankReconciliation $reconciliation, ReconcileBankAccount $reconcile): RedirectResponse
    {
        Gate::authorize('reconcile', $bankAccount);
        abort_unless($reconciliation->bank_account_id === $bankAccount->id, 404);

        $reconcile->undo($reconciliation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The reconciliation was undone.')]);

        return back();
    }
}
