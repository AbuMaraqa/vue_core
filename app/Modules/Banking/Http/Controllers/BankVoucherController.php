<?php

namespace App\Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Banking\Actions\BankVouchers\PostBankVoucher;
use App\Modules\Banking\Actions\CancelBankDocument;
use App\Modules\Banking\Enums\VoucherDirection;
use App\Modules\Banking\Http\Requests\StoreBankVoucherRequest;
use App\Modules\Banking\Http\Resources\BankVoucherResource;
use App\Modules\Banking\Models\BankVoucher;
use App\Modules\Banking\Printing\BankVoucherDocument;
use App\Modules\Banking\Support\BankingOptions;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Payment and receipt vouchers: money out of or into a bank or cash account
 * that is not a payment of an invoice.
 */
class BankVoucherController extends Controller
{
    public function index(Request $request, BankingOptions $options): Response
    {
        Gate::authorize('viewAny', BankVoucher::class);

        $vouchers = QueryBuilder::for(BankVoucher::query()->with(['bankAccount.currency', 'contact', 'currency']))
            ->allowedFilters(
                SearchFilter::on('number', 'payee', 'reference', 'description'),
                AllowedFilter::exact('direction'),
                AllowedFilter::exact('account', 'bank_account_id'),
                AllowedFilter::exact('status'),
                DateFilter::from(),
                DateFilter::to(),
            )
            ->defaultSort('-date', '-id')
            ->allowedSorts('date', 'number', 'total')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/banking/vouchers/Index', [
            'vouchers' => BankVoucherResource::collection($vouchers),
            'bankAccounts' => $options->bankAccounts(),
            'directions' => VoucherDirection::options(),
            'statuses' => DocumentStatus::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'direction' => $request->input('filter.direction'),
                'account' => $request->input('filter.account'),
                'status' => $request->input('filter.status'),
                'from' => $request->input('filter.from'),
                'to' => $request->input('filter.to'),
            ],
        ]);
    }

    public function create(Request $request, BankingOptions $options, AccountingOptions $accounting, Currencies $currencies): Response
    {
        Gate::authorize('create', BankVoucher::class);

        $direction = VoucherDirection::tryFrom($request->string('direction')->value()) ?? VoucherDirection::Out;

        // A simple form for one everyday expense, for people who do not know accounting.
        if ($direction === VoucherDirection::Out && $request->query('kind') === 'expense') {
            return Inertia::render('admin/banking/vouchers/Expense', [
                'bankAccounts' => $options->bankAccounts(),
                'expenseAccounts' => $accounting->accounts(AccountSubtype::OperatingExpense, AccountSubtype::OtherExpense),
                'baseCurrencyId' => $currencies->base()->id,
                'defaults' => ['date' => now()->toDateString()],
            ]);
        }

        return Inertia::render('admin/banking/vouchers/Create', [
            'direction' => $direction->value,
            'directionLabel' => $direction->label(),
            'bankAccounts' => $options->bankAccounts(),
            'accounts' => $accounting->accounts(),
            'costCenters' => $accounting->costCenters(),
            'taxes' => $accounting->taxes($direction === VoucherDirection::Out ? TaxScope::Purchases : TaxScope::Sales),
            'baseCurrencyId' => $currencies->base()->id,
            'defaults' => ['date' => now()->toDateString()],
        ]);
    }

    public function store(StoreBankVoucherRequest $request, PostBankVoucher $postVoucher): RedirectResponse
    {
        $voucher = $postVoucher->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Voucher :number posted.', ['number' => $voucher->number])]);

        return to_route('admin.banking.vouchers.show', $voucher);
    }

    public function show(BankVoucher $voucher): Response
    {
        Gate::authorize('view', $voucher);

        return Inertia::render('admin/banking/vouchers/Show', [
            'voucher' => BankVoucherResource::make($voucher->load([
                'bankAccount.currency', 'contact', 'currency', 'creator', 'lines.account', 'lines.costCenter', 'lines.tax', 'journalEntries',
            ])),
        ]);
    }

    /**
     * The printable voucher, for whoever can see it.
     */
    public function pdf(BankVoucher $voucher): BankVoucherDocument
    {
        Gate::authorize('view', $voucher);

        return new BankVoucherDocument($voucher);
    }

    public function cancel(BankVoucher $voucher, CancelBankDocument $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $voucher);

        $cancel->handle($voucher);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Voucher cancelled.')]);

        return to_route('admin.banking.vouchers.show', $voucher);
    }
}
