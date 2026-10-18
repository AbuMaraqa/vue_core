<?php

namespace App\Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\ChequeBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The cheque books of a bank account. Managing them is part of updating the
 * bank account.
 */
class ChequeBookController extends Controller
{
    public function store(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        Gate::authorize('update', $bankAccount);

        $validated = $request->validate([
            'first_number' => ['required', 'integer', 'min:1'],
            'last_number' => ['required', 'integer', 'gte:first_number', 'lte:'.((int) $request->input('first_number') + 10000)],
        ]);

        $bankAccount->chequeBooks()->create([...$validated, 'next_number' => $validated['first_number']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cheque book added.')]);

        return back();
    }

    /**
     * Retire a cheque book, or put it back in use.
     */
    public function update(Request $request, BankAccount $bankAccount, ChequeBook $chequeBook): RedirectResponse
    {
        Gate::authorize('update', $bankAccount);
        abort_unless($chequeBook->bank_account_id === $bankAccount->id, 404);

        $chequeBook->update($request->validate(['is_active' => ['required', 'boolean']]));

        return back();
    }

    public function destroy(BankAccount $bankAccount, ChequeBook $chequeBook): RedirectResponse
    {
        Gate::authorize('update', $bankAccount);
        abort_unless($chequeBook->bank_account_id === $bankAccount->id, 404);

        SafeDelete::delete($chequeBook);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cheque book deleted.')]);

        return back();
    }
}
