<?php

namespace App\Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Banking\Actions\BankTransfers\PostBankTransfer;
use App\Modules\Banking\Actions\CancelBankDocument;
use App\Modules\Banking\Http\Requests\StoreBankTransferRequest;
use App\Modules\Banking\Http\Resources\BankTransferResource;
use App\Modules\Banking\Models\BankTransfer;
use App\Modules\Banking\Printing\BankTransferDocument;
use App\Modules\Banking\Support\BankingOptions;
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
 * Transfers between the company's bank and cash accounts.
 */
class BankTransferController extends Controller
{
    public function index(Request $request, BankingOptions $options): Response
    {
        Gate::authorize('viewAny', BankTransfer::class);

        $transfers = QueryBuilder::for(BankTransfer::query()->with(['fromAccount.currency', 'toAccount.currency']))
            ->allowedFilters(
                SearchFilter::on('number', 'reference', 'description'),
                AllowedFilter::callback('account', fn (Builder $query, string $account) => $query->where(fn (Builder $either) => $either
                    ->where('from_bank_account_id', $account)
                    ->orWhere('to_bank_account_id', $account)))->delimiter(''),
                AllowedFilter::exact('status'),
                DateFilter::from(),
                DateFilter::to(),
            )
            ->defaultSort('-date', '-id')
            ->allowedSorts('date', 'number')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/banking/transfers/Index', [
            'transfers' => BankTransferResource::collection($transfers),
            'bankAccounts' => $options->bankAccounts(),
            'statuses' => DocumentStatus::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'account' => $request->input('filter.account'),
                'status' => $request->input('filter.status'),
                'from' => $request->input('filter.from'),
                'to' => $request->input('filter.to'),
            ],
        ]);
    }

    public function create(BankingOptions $options, Currencies $currencies): Response
    {
        Gate::authorize('create', BankTransfer::class);

        return Inertia::render('admin/banking/transfers/Create', [
            'bankAccounts' => $options->bankAccounts(),
            'baseCurrencyId' => $currencies->base()->id,
            'defaults' => ['date' => now()->toDateString()],
        ]);
    }

    public function store(StoreBankTransferRequest $request, PostBankTransfer $postTransfer): RedirectResponse
    {
        $transfer = $postTransfer->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer :number posted.', ['number' => $transfer->number])]);

        return to_route('admin.banking.transfers.show', $transfer);
    }

    public function show(BankTransfer $transfer): Response
    {
        Gate::authorize('view', $transfer);

        return Inertia::render('admin/banking/transfers/Show', [
            'transfer' => BankTransferResource::make($transfer->load(['fromAccount.currency', 'toAccount.currency', 'creator', 'journalEntries'])),
        ]);
    }

    /**
     * The printable transfer, for whoever can see it.
     */
    public function pdf(BankTransfer $transfer): BankTransferDocument
    {
        Gate::authorize('view', $transfer);

        return new BankTransferDocument($transfer);
    }

    public function cancel(BankTransfer $transfer, CancelBankDocument $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $transfer);

        $cancel->handle($transfer);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer cancelled.')]);

        return to_route('admin.banking.transfers.show', $transfer);
    }
}
