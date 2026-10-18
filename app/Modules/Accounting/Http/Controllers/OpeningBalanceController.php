<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Enter the balances carried over from a previous system. Whatever does not
 * balance goes to the opening balance equity account, so the entry is always
 * balanced; that account is then cleared once every balance is in.
 *
 * Customer and vendor balances are entered on each contact instead, so their
 * statements start with the right amount.
 */
class OpeningBalanceController extends Controller
{
    public function create(AccountingOptions $options): Response
    {
        Gate::authorize('Journal Entry Create');
        Gate::authorize('Journal Entry Post');

        $firstOpenYear = FiscalYear::query()->where('status', 'open')->orderBy('start_date')->first();

        return Inertia::render('admin/accounting/opening-balances/Create', [
            'accounts' => $options->accounts(),
            'defaults' => [
                'date' => ($firstOpenYear->start_date ?? now()->startOfYear())->toDateString(),
            ],
        ]);
    }

    public function store(Request $request, DefaultAccounts $defaults): RedirectResponse
    {
        Gate::authorize('Journal Entry Create');
        Gate::authorize('Journal Entry Post');

        $request->validate([
            'date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1', 'max:1000'],
            'lines.*.account_id' => ['required', 'integer', 'distinct', Rule::exists(Account::class, 'id')->where('is_group', false)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
        ]);

        $builder = JournalEntryBuilder::make($request->string('date')->value(), __('Opening balances'));
        $difference = Decimal::of(0);

        foreach ((array) $request->input('lines') as $line) {
            $amount = Decimal::of($line['debit'] ?? '0')->minus(Decimal::of($line['credit'] ?? '0'));
            $builder->debit((int) $line['account_id'], $amount);
            $difference = $difference->plus($amount);
        }

        $builder->credit($defaults->get(AccountRole::OpeningBalanceEquity), $difference);

        $entry = $builder->post();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Opening balances posted as :number.', ['number' => $entry->number])]);

        return to_route('admin.accounting.journal-entries.show', $entry);
    }
}
