<?php

namespace App\Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Banking\Support\BankingOptions;
use App\Modules\Contacts\Http\Controllers\ContactController;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Foundation\Support\Filters\DateFilter;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use App\Modules\Invoicing\Actions\Payments\CancelPayment;
use App\Modules\Invoicing\Actions\Payments\PostPayment;
use App\Modules\Invoicing\Enums\PaymentMethod;
use App\Modules\Invoicing\Enums\PaymentType;
use App\Modules\Invoicing\Http\Requests\StorePaymentRequest;
use App\Modules\Invoicing\Http\Resources\PaymentResource;
use App\Modules\Invoicing\Models\Allocation;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Payment;
use App\Modules\Invoicing\Printing\PaymentDocument;
use App\Modules\Invoicing\Support\Allocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Customer receipts (`admin.sales.receipts.*`) and vendor payments
 * (`admin.purchases.payments.*`); the type comes from the route.
 */
class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $type = PaymentType::fromRoute($request);
        Gate::authorize("{$type->permissionPrefix()} View List");

        $payments = QueryBuilder::for(Payment::query()->with(['contact', 'currency', 'bankAccount.currency', 'cheque', 'allocations'])->where('type', $type))
            ->allowedFilters(
                SearchFilter::on('number', 'reference', 'description'),
                AllowedFilter::exact('method'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('contact', 'contact_id'),
                DateFilter::from(),
                DateFilter::to(),
            )
            ->defaultSort('-date', '-id')
            ->allowedSorts('date', 'number', 'amount')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/invoicing/payments/Index', [
            'type' => $this->typeProps($type),
            'payments' => PaymentResource::collection($payments),
            'methods' => PaymentMethod::options(),
            'statuses' => DocumentStatus::options(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'method' => $request->input('filter.method'),
                'status' => $request->input('filter.status'),
                'contact' => $request->input('filter.contact'),
                'from' => $request->input('filter.from'),
                'to' => $request->input('filter.to'),
            ],
        ]);
    }

    /**
     * The form; choosing the contact reloads `openInvoices` with theirs.
     */
    public function create(Request $request, BankingOptions $banking, AccountingOptions $accounting, Currencies $currencies): Response
    {
        $type = PaymentType::fromRoute($request);
        Gate::authorize("{$type->permissionPrefix()} Create");

        $contact = Contact::query()->find($request->integer('contact_id'));

        return Inertia::render('admin/invoicing/payments/Create', [
            'type' => $this->typeProps($type),
            'methods' => PaymentMethod::options(),
            'bankAccounts' => $banking->bankAccounts(),
            'chequeBooks' => $type === PaymentType::Payment ? $banking->chequeBooks() : [],
            'currencies' => $accounting->currencies(),
            'contact' => $contact ? ContactController::option($contact) : null,
            'openInvoices' => fn (): array => $contact ? $this->openInvoices($type, $contact) : [],
            'defaults' => [
                'date' => now()->toDateString(),
                'currency_id' => $contact->currency_id ?? $currencies->base()->id,
                'invoice_id' => $request->integer('invoice_id') ?: null,
            ],
        ]);
    }

    public function store(StorePaymentRequest $request, PostPayment $postPayment): RedirectResponse
    {
        $type = $request->type();
        $payment = $postPayment->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':document :number posted.', ['document' => $type->label(), 'number' => $payment->number])]);

        return to_route("{$type->routeName()}.show", $payment);
    }

    public function show(Request $request, Payment $payment): Response
    {
        Gate::authorize('view', $payment);
        $this->ensureRouteType($request, $payment);

        $payment->load(['contact', 'currency', 'bankAccount.currency', 'cheque', 'allocations.invoice', 'journalEntries', 'creator']);

        return Inertia::render('admin/invoicing/payments/Show', [
            'type' => $this->typeProps($payment->type),
            'payment' => PaymentResource::make($payment),
            'openInvoices' => $payment->isEffective() ? array_values(array_filter(
                $this->openInvoices($payment->type, $payment->contact),
                fn (array $invoice): bool => $invoice['currency_id'] === $payment->currency_id,
            )) : [],
        ]);
    }

    /**
     * Allocate what is left of the payment to an open invoice.
     */
    public function allocate(Request $request, Payment $payment, Allocator $allocator): RedirectResponse
    {
        Gate::authorize('allocate', $payment);
        $this->ensureRouteType($request, $payment);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', Rule::exists(Invoice::class, 'id')->where('contact_id', $payment->contact_id)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
        ]);

        $allocator->allocatePayment($payment, Invoice::query()->whereKey($validated['invoice_id'])->firstOrFail(), (string) $validated['amount'], now());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The payment was allocated.')]);

        return back();
    }

    public function removeAllocation(Request $request, Payment $payment, Allocation $allocation, Allocator $allocator): RedirectResponse
    {
        Gate::authorize('allocate', $payment);
        $this->ensureRouteType($request, $payment);
        abort_unless($allocation->payment_id === $payment->id, 404);

        $allocator->remove($allocation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The allocation was removed.')]);

        return back();
    }

    public function cancel(Request $request, Payment $payment, CancelPayment $cancelPayment): RedirectResponse
    {
        Gate::authorize('cancel', $payment);
        $this->ensureRouteType($request, $payment);

        $cancelPayment->handle($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':document cancelled.', ['document' => $payment->type->label()])]);

        return to_route("{$payment->type->routeName()}.show", $payment);
    }

    public function pdf(Request $request, Payment $payment): PaymentDocument
    {
        Gate::authorize('view', $payment);
        $this->ensureRouteType($request, $payment);

        return new PaymentDocument($payment);
    }

    /**
     * The contact's posted invoices with something still due, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    protected function openInvoices(PaymentType $type, Contact $contact): array
    {
        return array_values(Invoice::query()
            ->with('currency')
            ->where('type', $type->invoiceType())
            ->where('contact_id', $contact->id)
            ->open()
            ->orderBy('due_date')
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'date' => $invoice->date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'currency_id' => $invoice->currency_id,
                'currency' => $invoice->currency->code,
                'total' => (string) $invoice->total,
                'amount_due' => $invoice->amountDue(),
                'is_overdue' => $invoice->isOverdue(),
            ])
            ->all());
    }

    protected function ensureRouteType(Request $request, Payment $payment): void
    {
        abort_unless(PaymentType::fromRoute($request) === $payment->type, 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeProps(PaymentType $type): array
    {
        return [
            'value' => $type->value,
            'label' => $type->label(),
            'route' => $type->routeName(),
            'permission' => $type->permissionPrefix(),
            'contact_type' => $type->contactType(),
            'invoice_route' => $type->invoiceType()->routeName(),
        ];
    }
}
