<?php

namespace App\Modules\Contacts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Http\Controllers\Concerns\RendersReports;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Contacts\Actions\Contacts\SaveContact;
use App\Modules\Contacts\Enums\ContactKind;
use App\Modules\Contacts\Exports\ContactsExporter;
use App\Modules\Contacts\Http\Requests\Contacts\SaveContactRequest;
use App\Modules\Contacts\Http\Resources\ContactResource;
use App\Modules\Contacts\Imports\ContactsImporter;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Printing\ContactStatementDocument;
use App\Modules\Contacts\Reports\ContactStatement;
use App\Modules\Contacts\Support\ContactBalances;
use App\Modules\Foundation\Exports\Report;
use App\Modules\Foundation\Http\Requests\ExportRequest;
use App\Modules\Foundation\Http\Requests\ImportRequest;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Customers and vendors. The page of a contact is its statement of account.
 */
class ContactController extends Controller
{
    use RendersReports;

    public function index(Request $request, ContactBalances $balances, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', Contact::class);

        $contacts = $this->query()->with('currency')->withExists('journalLines')->paginate(20)->withQueryString();
        $pageBalances = $balances->of($contacts->getCollection()->modelKeys());

        foreach ($contacts->getCollection() as $contact) {
            $contact->setAttribute('balance', $pageBalances[$contact->id]['balance'] ?? '0');
        }

        return Inertia::render('admin/contacts/Index', [
            'contacts' => ContactResource::collection($contacts),
            'filters' => [
                'search' => $request->input('filter.search'),
                'type' => $request->input('filter.type'),
                'status' => $request->input('filter.status'),
                'sort' => $request->input('sort', 'name'),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function create(Request $request, AccountingOptions $options): Response
    {
        Gate::authorize('create', Contact::class);

        return Inertia::render('admin/contacts/Create', [
            ...$this->formOptions($options),
            'type' => $request->query('type') === 'vendor' ? 'vendor' : 'customer',
        ]);
    }

    public function store(SaveContactRequest $request, SaveContact $saveContact): RedirectResponse
    {
        $contact = $saveContact->handle(new Contact, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact created.')]);

        return to_route('admin.contacts.show', $contact);
    }

    /**
     * Create a contact from the picker of a document form, with only its
     * essential fields, and return it as a picker option.
     */
    public function quickStore(SaveContactRequest $request, SaveContact $saveContact): JsonResponse
    {
        $contact = $saveContact->handle(new Contact, $request->toData());

        return response()->json(self::option($contact), SymfonyResponse::HTTP_CREATED);
    }

    /**
     * The contact's details, balances and statement of account for a period;
     * `?format=` downloads the statement.
     */
    public function show(Request $request, Contact $contact, ContactStatement $statement, ContactBalances $balances): SymfonyResponse
    {
        Gate::authorize('view', $contact);

        [$from, $to] = $this->reportPeriod($request);

        return $this->report(
            $request,
            'admin/contacts/Show',
            [
                'contact' => ContactResource::make($contact->load(['currency', 'receivableAccount', 'payableAccount'])),
                'balances' => $balances->of([$contact->id])[$contact->id] ?? ['receivable' => '0', 'payable' => '0', 'balance' => '0'],
                'statement' => $statement->generate($contact, $from, $to),
                'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ],
            fn (): Report => $statement->export($contact, $from, $to),
            exportPermission: 'Contact Export',
        );
    }

    /**
     * The printable statement of account for the period shown on the page.
     */
    public function pdf(Request $request, Contact $contact): ContactStatementDocument
    {
        Gate::authorize('view', $contact);

        [$from, $to] = $this->reportPeriod($request);

        return new ContactStatementDocument($contact, $from, $to);
    }

    public function edit(Contact $contact, AccountingOptions $options): Response
    {
        Gate::authorize('update', $contact);

        return Inertia::render('admin/contacts/Edit', [
            ...$this->formOptions($options),
            'contact' => ContactResource::make($contact),
        ]);
    }

    public function update(SaveContactRequest $request, Contact $contact, SaveContact $saveContact): RedirectResponse
    {
        $saveContact->handle($contact, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return to_route('admin.contacts.show', $contact);
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        SafeDelete::delete($contact);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact deleted.')]);

        return to_route('admin.contacts.index');
    }

    /**
     * Active contacts for the contact picker of document forms. Any staff
     * member who can fill in a document may search them.
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();
        $type = $request->string('type')->value();

        return response()->json(Contact::query()
            ->where('is_active', true)
            ->when($type === 'customers', fn (Builder $query) => $query->where('is_customer', true))
            ->when($type === 'vendors', fn (Builder $query) => $query->where('is_vendor', true))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $matching) => $matching
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(fn (Contact $contact): array => self::option($contact))
            ->values());
    }

    /**
     * A contact as an option of the contact picker.
     *
     * @return array{value: int, label: string, hint: string, currency_id: int|null, payment_terms_days: int}
     */
    public static function option(Contact $contact): array
    {
        return [
            'value' => $contact->id,
            'label' => $contact->name,
            'hint' => $contact->code,
            'currency_id' => $contact->currency_id,
            'payment_terms_days' => $contact->payment_terms_days,
        ];
    }

    /**
     * The permission is checked by the route, before the format is validated.
     */
    public function export(ExportRequest $request): SymfonyResponse
    {
        return ContactsExporter::from($this->query()->with('currency'))->download($request->exportFormat());
    }

    public function import(ImportRequest $request, SaveContact $saveContact): RedirectResponse
    {
        $result = (new ContactsImporter($saveContact))->import($request->spreadsheet());

        Inertia::flash([
            'toast' => [
                'type' => $result->hasFailures() ? 'warning' : 'success',
                'message' => trans_choice('{0} No contacts were imported.|{1} One contact was imported.|[2,*] :count contacts were imported.', $result->imported),
            ],
            'importResult' => $result->toArray(),
        ]);

        return back();
    }

    public function template(SaveContact $saveContact): SymfonyResponse
    {
        return (new ContactsImporter($saveContact))->template('contacts-import-template.xlsx');
    }

    /**
     * The filtered and sorted list, shared by the page and its exports.
     *
     * @return QueryBuilder<Contact>
     */
    protected function query(): QueryBuilder
    {
        return QueryBuilder::for(Contact::class)
            ->allowedFilters(
                SearchFilter::on('code', 'name', 'contact_person', 'email', 'phone', 'mobile', 'tax_number'),
                AllowedFilter::callback('type', fn (Builder $query, string $type) => match ($type) {
                    'customers' => $query->where('is_customer', true),
                    'vendors' => $query->where('is_vendor', true),
                    default => $query,
                })->delimiter(''),
                AllowedFilter::callback('status', fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))->delimiter(''),
            )
            ->allowedSorts('name', 'code', 'created_at')
            ->defaultSort('name');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(AccountingOptions $options): array
    {
        return [
            'kinds' => ContactKind::options(),
            'currencies' => $options->currencies(),
            'receivableAccounts' => $options->accounts(AccountSubtype::Receivable),
            'payableAccounts' => $options->accounts(AccountSubtype::Payable),
        ];
    }
}
