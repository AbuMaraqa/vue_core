<?php

namespace App\Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Support\BankingOptions;
use App\Modules\Contacts\Http\Controllers\ContactController;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Support\InventoryOptions;
use App\Modules\Invoicing\Actions\Invoices\CancelInvoice;
use App\Modules\Invoicing\Actions\Invoices\PayInvoiceInFull;
use App\Modules\Invoicing\Actions\Invoices\PostInvoice;
use App\Modules\Invoicing\Actions\Invoices\SaveInvoice;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Enums\PaymentStatus;
use App\Modules\Invoicing\Http\Requests\SaveInvoiceRequest;
use App\Modules\Invoicing\Http\Resources\InvoiceResource;
use App\Modules\Invoicing\Models\Allocation;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\InvoiceLine;
use App\Modules\Invoicing\Printing\InvoiceDocument;
use App\Modules\Invoicing\Support\Allocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Sales invoices, credit notes, purchase bills and debit notes. Each type
 * has its own routes (`admin.sales.invoices.*`, ...) that all lead here;
 * the type comes from the route.
 */
class InvoiceController extends Controller
{
    public function index(Request $request, Currencies $currencies): Response
    {
        $type = InvoiceType::fromRoute($request);
        Gate::authorize("{$type->permissionPrefix()} View List");

        $query = QueryBuilder::for(Invoice::query()->with(['contact', 'currency'])->where('type', $type))
            ->allowedFilters(
                SearchFilter::on('number', 'reference'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('contact', 'contact_id'),
                AllowedFilter::callback('payment', fn (Builder $query, string $payment) => match ($payment) {
                    PaymentStatus::Paid->value => $query->where('status', DocumentStatus::Posted)->whereColumn('paid_amount', '>=', 'total'),
                    PaymentStatus::PartiallyPaid->value => $this->open($query)->where('paid_amount', '>', 0),
                    PaymentStatus::Unpaid->value => $this->open($query)->where('paid_amount', 0),
                    'overdue' => $this->open($query)->where('due_date', '<', today()),
                    default => $query,
                })->delimiter(''),
                DateFilter::from(),
                DateFilter::to(),
            )
            ->defaultSort('-date', '-id')
            ->allowedSorts('date', 'number', 'due_date', 'total');

        $open = Invoice::query()->where('type', $type)->open()->get(['total', 'paid_amount', 'exchange_rate', 'due_date']);
        $baseDue = fn (iterable $invoices): string => Decimal::round(Decimal::sum(collect($invoices)->map(
            fn (Invoice $invoice): string => Decimal::multiply($invoice->amountDue(), (string) $invoice->exchange_rate),
        )));

        return Inertia::render('admin/invoicing/invoices/Index', [
            'type' => $this->typeProps($type),
            'invoices' => InvoiceResource::collection($query->paginate(20)->withQueryString()),
            'summary' => [
                'open' => $baseDue($open),
                'overdue' => $baseDue($open->filter(fn (Invoice $invoice): bool => $invoice->due_date->lessThan(now()->startOfDay()))),
                'drafts' => Invoice::query()->where('type', $type)->where('status', DocumentStatus::Draft)->count(),
            ],
            'statuses' => DocumentStatus::options(),
            'paymentStatuses' => [...PaymentStatus::options(), ['value' => 'overdue', 'label' => __('Overdue')]],
            'filters' => [
                'search' => $request->input('filter.search'),
                'status' => $request->input('filter.status'),
                'payment' => $request->input('filter.payment'),
                'contact' => $request->input('filter.contact'),
                'from' => $request->input('filter.from'),
                'to' => $request->input('filter.to'),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function create(Request $request, AccountingOptions $accounting, InventoryOptions $inventory, BankingOptions $banking, Currencies $currencies, AccountingSettings $settings): Response
    {
        $type = InvoiceType::fromRoute($request);
        Gate::authorize("{$type->permissionPrefix()} Create");

        $contact = Contact::query()->find($request->integer('contact_id'));
        $original = $type->isReturn()
            ? Invoice::query()->with(['lines.item.unit', 'contact'])->where('type', $type->counterpart())->posted()->find($request->integer('original_invoice_id'))
            : null;
        $contact ??= $original?->contact;

        return Inertia::render('admin/invoicing/invoices/Create', [
            ...$this->formOptions($type, $accounting, $inventory, $banking),
            'defaults' => [
                'contact' => $contact ? ContactController::option($contact) : null,
                'date' => now()->toDateString(),
                'currency_id' => $original->currency_id ?? $contact->currency_id ?? $currencies->base()->id,
                'warehouse_id' => $original->warehouse_id ?? ($inventory->warehouses()[0]['value'] ?? null),
                'prices_include_tax' => $original->prices_include_tax ?? $settings->prices_include_tax,
                'original_invoice' => $original ? ['id' => $original->id, 'number' => $original->number] : null,
                'is_opening' => $request->boolean('opening') && ! $type->isReturn(),
                'lines' => $original ? $original->lines->map(fn (InvoiceLine $line): array => [
                    'item' => $line->item ? app(InventoryOptions::class)->itemOption($line->item) : null,
                    'item_id' => $line->item_id,
                    'description' => $line->description,
                    'account_id' => $line->account_id,
                    'cost_center_id' => $line->cost_center_id,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => (string) $line->unit_price,
                    'discount_percent' => (string) $line->discount_percent,
                    'tax_id' => $line->tax_id,
                ])->values() : [],
            ],
        ]);
    }

    /**
     * Save the document, post it when asked (and pay it in full with
     * `paid_now`), then show it, or open a new form with `and_new`.
     */
    public function store(SaveInvoiceRequest $request, SaveInvoice $saveInvoice, PostInvoice $postInvoice, PayInvoiceInFull $payInvoiceInFull): RedirectResponse
    {
        $type = $request->type();
        $isPaid = false;

        $invoice = DB::transaction(function () use ($request, $saveInvoice, $postInvoice, $payInvoiceInFull, $type, &$isPaid): Invoice {
            $invoice = $saveInvoice->handle(new Invoice, $request->toData());

            if ($request->boolean('post')) {
                Gate::authorize("{$type->permissionPrefix()} Post");
                $invoice = $postInvoice->handle($invoice);
                $isPaid = $this->payInFullIfAsked($request, $invoice, $payInvoiceInFull);
            }

            return $invoice;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => match (true) {
            $isPaid => __(':document :number posted and paid.', ['document' => $type->label(), 'number' => $invoice->number]),
            $invoice->number !== null => __(':document :number posted.', ['document' => $type->label(), 'number' => $invoice->number]),
            default => __(':document saved as a draft.', ['document' => $type->label()]),
        }]);

        if ($request->boolean('and_new')) {
            return to_route("{$type->routeName()}.create", $invoice->is_opening ? ['opening' => 1] : []);
        }

        return to_route("{$type->routeName()}.show", $invoice);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);
        $this->ensureRouteType($request, $invoice);

        $invoice->load([
            'contact', 'currency', 'warehouse', 'originalInvoice', 'creator', 'lines.item.unit', 'lines.account',
            'allocations.payment', 'allocations.creditNote', 'allocations.journalEntry', 'appliedTo.invoice', 'journalEntries',
        ]);

        $notes = $invoice->status === DocumentStatus::Posted && ! $invoice->type->isReturn()
            ? Invoice::query()
                ->where('type', $invoice->type->counterpart())
                ->where('contact_id', $invoice->contact_id)
                ->where('currency_id', $invoice->currency_id)
                ->open()
                ->get()
                ->map(fn (Invoice $note): array => ['value' => $note->id, 'label' => $note->label(), 'available' => $note->amountDue()])
                ->values()
            : [];

        return Inertia::render('admin/invoicing/invoices/Show', [
            'type' => $this->typeProps($invoice->type),
            'invoice' => InvoiceResource::make($invoice),
            'openNotes' => $notes,
        ]);
    }

    public function edit(Request $request, Invoice $invoice, AccountingOptions $accounting, InventoryOptions $inventory, BankingOptions $banking): Response
    {
        Gate::authorize('update', $invoice);
        $this->ensureRouteType($request, $invoice);

        $invoice->load(['contact', 'currency', 'originalInvoice', 'lines.item.unit']);

        return Inertia::render('admin/invoicing/invoices/Edit', [
            ...$this->formOptions($invoice->type, $accounting, $inventory, $banking),
            'invoice' => InvoiceResource::make($invoice),
            'contact' => ContactController::option($invoice->contact),
            'lineItems' => $invoice->lines->mapWithKeys(fn (InvoiceLine $line): array => [
                $line->id => $line->item ? $inventory->itemOption($line->item) : null,
            ]),
        ]);
    }

    public function update(SaveInvoiceRequest $request, Invoice $invoice, SaveInvoice $saveInvoice, PostInvoice $postInvoice, PayInvoiceInFull $payInvoiceInFull): RedirectResponse
    {
        $this->ensureRouteType($request, $invoice);

        DB::transaction(function () use ($request, $invoice, $saveInvoice, $postInvoice, $payInvoiceInFull): void {
            $saveInvoice->handle($invoice, $request->toData());

            if ($request->boolean('post')) {
                Gate::authorize('post', $invoice);
                $this->payInFullIfAsked($request, $postInvoice->handle($invoice), $payInvoiceInFull);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':document updated.', ['document' => $invoice->type->label()])]);

        return to_route("{$invoice->type->routeName()}.show", $invoice);
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);
        $this->ensureRouteType($request, $invoice);

        $invoice->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft deleted.')]);

        return to_route("{$invoice->type->routeName()}.index");
    }

    public function post(Request $request, Invoice $invoice, PostInvoice $postInvoice): RedirectResponse
    {
        Gate::authorize('post', $invoice);
        $this->ensureRouteType($request, $invoice);

        $postInvoice->handle($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':document :number posted.', ['document' => $invoice->type->label(), 'number' => $invoice->fresh()?->number])]);

        return to_route("{$invoice->type->routeName()}.show", $invoice);
    }

    public function cancel(Request $request, Invoice $invoice, CancelInvoice $cancelInvoice): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);
        $this->ensureRouteType($request, $invoice);

        $cancelInvoice->handle($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':document cancelled.', ['document' => $invoice->type->label()])]);

        return to_route("{$invoice->type->routeName()}.show", $invoice);
    }

    /**
     * Apply an open credit note (or debit note) of the contact to the invoice.
     */
    public function applyNote(Request $request, Invoice $invoice, Allocator $allocator): RedirectResponse
    {
        Gate::authorize('settle', $invoice);
        $this->ensureRouteType($request, $invoice);

        $validated = $request->validate([
            'note_id' => ['required', 'integer', Rule::exists(Invoice::class, 'id')->where('type', $invoice->type->counterpart()->value)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
        ]);

        $allocator->applyNote(Invoice::query()->whereKey($validated['note_id'])->firstOrFail(), $invoice, (string) $validated['amount']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The note was applied.')]);

        return back();
    }

    /**
     * Remove a payment or note from the invoice: it is open again.
     */
    public function removeAllocation(Request $request, Invoice $invoice, Allocation $allocation, Allocator $allocator): RedirectResponse
    {
        Gate::authorize('settle', $invoice);
        $this->ensureRouteType($request, $invoice);
        abort_unless($allocation->invoice_id === $invoice->id, 404);

        $allocator->remove($allocation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The allocation was removed.')]);

        return back();
    }

    public function pdf(Request $request, Invoice $invoice): InvoiceDocument
    {
        Gate::authorize('view', $invoice);
        $this->ensureRouteType($request, $invoice);

        return new InvoiceDocument($invoice);
    }

    /**
     * Pay the just-posted invoice or bill in full from the chosen account when
     * the form asked for it, with the same permission as recording a receipt
     * or vendor payment.
     */
    protected function payInFullIfAsked(SaveInvoiceRequest $request, Invoice $invoice, PayInvoiceInFull $payInvoiceInFull): bool
    {
        $paymentType = $invoice->type->paymentType();

        if ($paymentType === null || ! $request->paysNow()) {
            return false;
        }

        Gate::authorize("{$paymentType->permissionPrefix()} Create");

        $payInvoiceInFull->handle($invoice, BankAccount::query()->findOrFail($request->integer('payment_account_id')));

        return true;
    }

    /**
     * Posted documents with something still due.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function open(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Posted)->whereColumn('paid_amount', '<', 'total');
    }

    /**
     * A document is only reached through the routes of its own type.
     */
    protected function ensureRouteType(Request $request, Invoice $invoice): void
    {
        abort_unless(InvoiceType::fromRoute($request) === $invoice->type, 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeProps(InvoiceType $type): array
    {
        return [
            'value' => $type->value,
            'label' => $type->label(),
            'plural' => $type->pluralLabel(),
            'is_sales' => $type->isSales(),
            'is_return' => $type->isReturn(),
            'route' => $type->routeName(),
            'permission' => $type->permissionPrefix(),
            'contact_type' => $type->contactType(),
            'counterpart' => ['value' => $type->counterpart()->value, 'label' => $type->counterpart()->label(), 'route' => $type->counterpart()->routeName()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(InvoiceType $type, AccountingOptions $accounting, InventoryOptions $inventory, BankingOptions $banking): array
    {
        return [
            'bankAccounts' => $type->paymentType() !== null ? $banking->bankAccounts() : [],
            'units' => $inventory->units(),
            'type' => $this->typeProps($type),
            'currencies' => $accounting->currencies(),
            'taxes' => $accounting->taxes($type->taxScope()),
            'accounts' => $type->isSales()
                ? $accounting->accounts(AccountSubtype::OperatingRevenue, AccountSubtype::OtherRevenue)
                : $accounting->accounts(),
            'costCenters' => $accounting->costCenters(),
            'warehouses' => $inventory->warehouses(),
            'hasItems' => Item::query()->where('is_active', true)->exists(),
        ];
    }
}
