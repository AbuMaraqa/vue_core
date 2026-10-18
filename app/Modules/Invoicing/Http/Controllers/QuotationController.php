<?php

namespace App\Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\TaxScope;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Contacts\Http\Controllers\ContactController;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Support\InventoryOptions;
use App\Modules\Invoicing\Actions\Quotations\ChangeQuotationStatus;
use App\Modules\Invoicing\Actions\Quotations\ConvertQuotationToInvoice;
use App\Modules\Invoicing\Actions\Quotations\SaveQuotation;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Enums\QuotationStatus;
use App\Modules\Invoicing\Http\Requests\SaveQuotationRequest;
use App\Modules\Invoicing\Http\Resources\QuotationResource;
use App\Modules\Invoicing\Models\Quotation;
use App\Modules\Invoicing\Models\QuotationLine;
use App\Modules\Invoicing\Printing\QuotationDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Sales quotations: price offers to customers, printed and sent, then
 * accepted, rejected or converted to a sales invoice.
 */
class QuotationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('Sales Quotation View List');

        $query = QueryBuilder::for(Quotation::query()->with(['contact', 'currency']))
            ->allowedFilters(
                SearchFilter::on('number', 'reference'),
                AllowedFilter::callback('status', function (Builder $query, string $status): void {
                    $status = QuotationStatus::tryFrom($status);

                    if ($status !== null) {
                        Quotation::whereDisplayStatus($query, $status);
                    }
                })->delimiter(''),
                AllowedFilter::exact('contact', 'contact_id'),
                AllowedFilter::callback('from', fn (Builder $query, string $date) => $query->whereDate('date', '>=', $date))->delimiter(''),
                AllowedFilter::callback('to', fn (Builder $query, string $date) => $query->whereDate('date', '<=', $date))->delimiter(''),
            )
            ->defaultSort('-date', '-id')
            ->allowedSorts('date', 'number', 'valid_until', 'total');

        $count = function (QuotationStatus ...$statuses): int {
            $query = Quotation::query()->where(function (Builder $matching) use ($statuses): void {
                foreach ($statuses as $status) {
                    $matching->orWhere(fn (Builder $inStatus) => Quotation::whereDisplayStatus($inStatus, $status));
                }
            });

            return $query->count();
        };

        return Inertia::render('admin/invoicing/quotations/Index', [
            'quotations' => QuotationResource::collection($query->paginate(20)->withQueryString()),
            'summary' => [
                'waiting' => $count(QuotationStatus::Draft, QuotationStatus::Sent),
                'accepted' => $count(QuotationStatus::Accepted),
                'expired' => $count(QuotationStatus::Expired),
            ],
            'statuses' => QuotationStatus::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'status' => $request->input('filter.status'),
                'contact' => $request->input('filter.contact'),
                'from' => $request->input('filter.from'),
                'to' => $request->input('filter.to'),
            ],
        ]);
    }

    public function create(Request $request, AccountingOptions $accounting, Currencies $currencies, AccountingSettings $settings): Response
    {
        Gate::authorize('Sales Quotation Create');

        $contact = Contact::query()->where('is_customer', true)->find($request->integer('contact_id'));

        return Inertia::render('admin/invoicing/quotations/Create', [
            ...$this->formOptions($accounting),
            'defaults' => [
                'contact' => $contact ? ContactController::option($contact) : null,
                'date' => now()->toDateString(),
                'currency_id' => $contact->currency_id ?? $currencies->base()->id,
                'prices_include_tax' => $settings->prices_include_tax,
                'validity_days' => SaveQuotation::DEFAULT_VALIDITY_DAYS,
            ],
        ]);
    }

    public function store(SaveQuotationRequest $request, SaveQuotation $saveQuotation): RedirectResponse
    {
        $quotation = $saveQuotation->handle(new Quotation, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation :number saved.', ['number' => $quotation->number])]);

        return to_route('admin.sales.quotations.show', $quotation);
    }

    public function show(Quotation $quotation): Response
    {
        Gate::authorize('view', $quotation);

        $quotation->load(['contact', 'currency', 'invoice', 'creator', 'lines.item.unit']);

        return Inertia::render('admin/invoicing/quotations/Show', [
            'quotation' => QuotationResource::make($quotation),
        ]);
    }

    public function edit(Quotation $quotation, AccountingOptions $accounting, InventoryOptions $inventory): Response
    {
        Gate::authorize('update', $quotation);

        $quotation->load(['contact', 'currency', 'lines.item.unit']);

        return Inertia::render('admin/invoicing/quotations/Edit', [
            ...$this->formOptions($accounting),
            'quotation' => QuotationResource::make($quotation),
            'contact' => ContactController::option($quotation->contact),
            'lineItems' => $quotation->lines->mapWithKeys(fn (QuotationLine $line): array => [
                $line->id => $line->item ? $inventory->itemOption($line->item) : null,
            ]),
        ]);
    }

    public function update(SaveQuotationRequest $request, Quotation $quotation, SaveQuotation $saveQuotation): RedirectResponse
    {
        $saveQuotation->handle($quotation, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation :number updated.', ['number' => $quotation->number])]);

        return to_route('admin.sales.quotations.show', $quotation);
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('delete', $quotation);

        $quotation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation :number deleted.', ['number' => $quotation->number])]);

        return to_route('admin.sales.quotations.index');
    }

    /**
     * Mark the quotation sent, accepted or rejected.
     */
    public function status(Request $request, Quotation $quotation, ChangeQuotationStatus $changeStatus): RedirectResponse
    {
        Gate::authorize('changeStatus', $quotation);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(QuotationStatus::class)->only(QuotationStatus::stored())],
        ]);

        $status = QuotationStatus::from($validated['status']);
        $changeStatus->handle($quotation, $status);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation marked :status.', ['status' => $status->label()])]);

        return back();
    }

    /**
     * Create the sales invoice of the quotation and open it for review.
     */
    public function convert(Quotation $quotation, ConvertQuotationToInvoice $convert): RedirectResponse
    {
        Gate::authorize('convert', $quotation);

        $invoice = $convert->handle($quotation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('A draft invoice was created from :number. Check it, then post it.', ['number' => $quotation->number])]);

        return to_route(InvoiceType::SalesInvoice->routeName().'.edit', $invoice);
    }

    public function pdf(Quotation $quotation): QuotationDocument
    {
        Gate::authorize('view', $quotation);

        return new QuotationDocument($quotation);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(AccountingOptions $accounting): array
    {
        return [
            'currencies' => $accounting->currencies(),
            'taxes' => $accounting->taxes(TaxScope::Sales),
            'hasItems' => Item::query()->where('is_active', true)->exists(),
        ];
    }
}
