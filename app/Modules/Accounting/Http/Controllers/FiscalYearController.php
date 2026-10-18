<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Actions\FiscalYears\CloseFiscalYear;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Posting\Ledger;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fiscal years: create them, close them at year end, and reopen the last
 * closed year when a correction is needed.
 */
class FiscalYearController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('Fiscal Year View List');

        return Inertia::render('admin/accounting/fiscal-years/Index', [
            'years' => FiscalYear::query()->with('closingEntry')->orderByDesc('start_date')->get()->map(fn (FiscalYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'start_date' => $year->start_date->toDateString(),
                'end_date' => $year->end_date->toDateString(),
                'status' => $year->status->value,
                'status_label' => $year->status->label(),
                'closed_at' => $year->closed_at?->toIso8601String(),
                'closing_entry' => $year->closingEntry ? ['id' => $year->closingEntry->id, 'number' => $year->closingEntry->number] : null,
            ]),
            'can' => [
                'create' => Gate::allows('Fiscal Year Create'),
                'close' => Gate::allows('Fiscal Year Close'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('Fiscal Year Create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date', function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                $overlaps = FiscalYear::query()
                    ->whereDate('start_date', '<=', (string) $value)
                    ->whereDate('end_date', '>=', $request->string('start_date')->value())
                    ->exists();

                if ($overlaps) {
                    $fail(__('The dates overlap another fiscal year.'));
                }
            }],
        ]);

        FiscalYear::query()->create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fiscal year created.')]);

        return to_route('admin.accounting.fiscal-years.index');
    }

    public function close(FiscalYear $fiscalYear, CloseFiscalYear $closeYear): RedirectResponse
    {
        Gate::authorize('Fiscal Year Close');

        $closeYear->handle($fiscalYear);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The fiscal year :name was closed.', ['name' => $fiscalYear->name])]);

        return to_route('admin.accounting.fiscal-years.index');
    }

    /**
     * Reopen the latest closed year: its closing entry is reversed, so the
     * year's revenue and expenses are back on their accounts.
     */
    public function reopen(FiscalYear $fiscalYear, Ledger $ledger): RedirectResponse
    {
        Gate::authorize('Fiscal Year Close');

        DB::transaction(function () use ($fiscalYear, $ledger): void {
            $laterClosedYear = FiscalYear::query()
                ->where('status', FiscalYearStatus::Closed)
                ->whereDate('start_date', '>', $fiscalYear->end_date)
                ->exists();

            if ($fiscalYear->isOpen() || $laterClosedYear) {
                throw new AccountingException(__('Only the latest closed fiscal year can be reopened.'));
            }

            $fiscalYear->forceFill(['status' => FiscalYearStatus::Open, 'closed_at' => null, 'closed_by' => null])->save();

            if ($fiscalYear->closingEntry) {
                $ledger->reverse($fiscalYear->closingEntry, $fiscalYear->end_date, __('Reopening of fiscal year :name', ['name' => $fiscalYear->name]));
            }

            $fiscalYear->forceFill(['closing_entry_id' => null])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The fiscal year :name was reopened.', ['name' => $fiscalYear->name])]);

        return to_route('admin.accounting.fiscal-years.index');
    }
}
