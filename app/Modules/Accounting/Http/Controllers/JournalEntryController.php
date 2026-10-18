<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Actions\JournalEntries\SaveJournalEntry;
use App\Modules\Accounting\Data\JournalEntryData;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Http\Requests\JournalEntries\SaveJournalEntryRequest;
use App\Modules\Accounting\Http\Resources\JournalEntryResource;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Printing\JournalEntryDocument;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Journal entries: manual entries are written here; entries of documents are
 * listed here too, read-only, with a link to their document.
 */
class JournalEntryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $entries = QueryBuilder::for(JournalEntry::query()->with('currency'))
            ->allowedFilters(
                SearchFilter::on('number', 'reference', 'description'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('origin', fn (Builder $query, string $origin) => $origin === 'manual'
                    ? $query->whereNull('source_type')
                    : $query->whereNotNull('source_type'))->delimiter(''),
                DateFilter::from(),
                DateFilter::to(),
            )
            ->defaultSort('-date', '-id')
            ->allowedSorts('date', 'number')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/accounting/journal-entries/Index', [
            'entries' => JournalEntryResource::collection($entries),
            'statuses' => JournalEntryStatus::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'status' => $request->input('filter.status'),
                'origin' => $request->input('filter.origin'),
                'from' => $request->input('filter.from'),
                'to' => $request->input('filter.to'),
            ],
        ]);
    }

    public function create(AccountingOptions $options, Currencies $currencies): Response
    {
        Gate::authorize('create', JournalEntry::class);

        return Inertia::render('admin/accounting/journal-entries/Create', [
            ...$this->formOptions($options),
            'defaults' => [
                'date' => now()->toDateString(),
                'currency_id' => $currencies->base()->id,
            ],
        ]);
    }

    public function store(SaveJournalEntryRequest $request, SaveJournalEntry $saveEntry): RedirectResponse
    {
        $entry = $saveEntry->handle(new JournalEntry, JournalEntryData::from($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Journal entry saved as a draft.')]);

        return to_route('admin.accounting.journal-entries.show', $entry);
    }

    public function show(JournalEntry $journalEntry): Response
    {
        Gate::authorize('view', $journalEntry);

        return Inertia::render('admin/accounting/journal-entries/Show', [
            'entry' => JournalEntryResource::make($journalEntry->load([
                'currency', 'lines.account', 'lines.costCenter', 'reversalOf', 'reversedBy', 'creator', 'poster', 'source',
            ])),
        ]);
    }

    /**
     * The printable journal voucher.
     */
    public function pdf(JournalEntry $journalEntry): JournalEntryDocument
    {
        Gate::authorize('view', $journalEntry);

        return new JournalEntryDocument($journalEntry);
    }

    public function edit(JournalEntry $journalEntry, AccountingOptions $options): Response
    {
        Gate::authorize('update', $journalEntry);

        return Inertia::render('admin/accounting/journal-entries/Edit', [
            ...$this->formOptions($options),
            'entry' => JournalEntryResource::make($journalEntry->load(['currency', 'lines'])),
        ]);
    }

    public function update(SaveJournalEntryRequest $request, JournalEntry $journalEntry, SaveJournalEntry $saveEntry): RedirectResponse
    {
        $saveEntry->handle($journalEntry, JournalEntryData::from($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Journal entry updated.')]);

        return to_route('admin.accounting.journal-entries.show', $journalEntry);
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        Gate::authorize('delete', $journalEntry);

        $journalEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Journal entry deleted.')]);

        return to_route('admin.accounting.journal-entries.index');
    }

    public function post(JournalEntry $journalEntry, Ledger $ledger): RedirectResponse
    {
        Gate::authorize('post', $journalEntry);

        $entry = $ledger->post($journalEntry);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Journal entry :number posted.', ['number' => $entry->number])]);

        return to_route('admin.accounting.journal-entries.show', $entry);
    }

    public function reverse(Request $request, JournalEntry $journalEntry, Ledger $ledger): RedirectResponse
    {
        Gate::authorize('reverse', $journalEntry);

        $request->validate(['date' => ['required', 'date']]);

        $reversal = $ledger->reverse($journalEntry, $request->date('date'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Journal entry reversed by :number.', ['number' => $reversal->number])]);

        return to_route('admin.accounting.journal-entries.show', $reversal);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(AccountingOptions $options): array
    {
        return [
            'accounts' => $options->accounts(),
            'costCenters' => $options->costCenters(),
            'currencies' => $options->currencies(),
        ];
    }
}
