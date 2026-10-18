<?php

namespace App\Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Enums\ChequeAction;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Enums\ChequeStatus;
use App\Modules\Banking\Exports\ChequesExporter;
use App\Modules\Banking\Http\Requests\ChequeActionRequest;
use App\Modules\Banking\Http\Requests\StoreChequeRequest;
use App\Modules\Banking\Http\Resources\ChequeResource;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Banking\Models\ChequeEvent;
use App\Modules\Banking\Printing\ChequeDocument;
use App\Modules\Banking\Support\BankingOptions;
use App\Modules\Banking\Support\ChequeRegister;
use App\Modules\Foundation\Http\Requests\ExportRequest;
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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The cheque register: received and issued cheques, their due dates and
 * every step of their life.
 */
class ChequeController extends Controller
{
    public function index(Request $request, BankingOptions $options, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', Cheque::class);

        $cheques = $this->query()->with(['contact', 'bankAccount.currency', 'currency'])->paginate(25)->withQueryString();

        return Inertia::render('admin/banking/cheques/Index', [
            'cheques' => ChequeResource::collection($cheques),
            'summary' => $this->summary(),
            'directions' => ChequeDirection::options(),
            'statuses' => ChequeStatus::options(),
            'bankAccounts' => $options->bankAccounts(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'direction' => $request->input('filter.direction'),
                'status' => $request->input('filter.status'),
                'open' => $request->boolean('filter.open'),
                'due_from' => $request->input('filter.due_from'),
                'due_to' => $request->input('filter.due_to'),
                'sort' => $request->input('sort', 'due_date'),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function create(Request $request, BankingOptions $options, AccountingOptions $accounting, Currencies $currencies): Response
    {
        Gate::authorize('create', Cheque::class);

        $direction = ChequeDirection::tryFrom($request->string('direction')->value()) ?? ChequeDirection::Incoming;

        return Inertia::render('admin/banking/cheques/Create', [
            'direction' => $direction->value,
            'directionLabel' => $direction->label(),
            'bankAccounts' => $options->bankAccounts(),
            'chequeBooks' => $options->chequeBooks(),
            'currencies' => $accounting->currencies(),
            'accounts' => $accounting->accounts(),
            'defaults' => [
                'issue_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'currency_id' => $currencies->base()->id,
            ],
        ]);
    }

    public function store(StoreChequeRequest $request, ChequeRegister $register): RedirectResponse
    {
        $data = $request->toData();
        $cheque = $data->direction === ChequeDirection::Incoming ? $register->receive($data) : $register->issue($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cheque :number recorded.', ['number' => $cheque->number])]);

        return to_route('admin.banking.cheques.show', $cheque);
    }

    public function show(Request $request, Cheque $cheque, BankingOptions $options): Response
    {
        Gate::authorize('view', $cheque);

        return Inertia::render('admin/banking/cheques/Show', [
            'cheque' => ChequeResource::make($cheque->load([
                'contact', 'bankAccount.currency', 'currency', 'counterAccount', 'endorsedTo', 'events.journalEntry', 'events.creator',
            ])),
            'bankAccounts' => array_values(array_filter(
                $options->bankAccounts(),
                fn (array $bankAccount): bool => $bankAccount['currency_id'] === $cheque->currency_id,
            )),
            'canUndo' => $cheque->events->contains(fn (ChequeEvent $event): bool => ! $event->is_undone && $event->from_status !== null)
                && ($request->user()?->can('manage', $cheque) ?? false),
        ]);
    }

    /**
     * The printable record of the cheque and its history, for whoever can see it.
     */
    public function pdf(Cheque $cheque): ChequeDocument
    {
        Gate::authorize('view', $cheque);

        return new ChequeDocument($cheque);
    }

    public function perform(ChequeActionRequest $request, Cheque $cheque, ChequeRegister $register): RedirectResponse
    {
        $action = ChequeAction::from($request->string('action')->value());

        $register->perform(
            $cheque,
            $action,
            $request->string('date')->value(),
            $request->integer('bank_account_id') ?: null,
            $request->integer('endorse_to_contact_id') ?: null,
            $request->input('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cheque :number: :action done.', ['number' => $cheque->number, 'action' => $action->label()])]);

        return to_route('admin.banking.cheques.show', $cheque);
    }

    public function undo(Cheque $cheque, ChequeRegister $register): RedirectResponse
    {
        Gate::authorize('manage', $cheque);

        $register->undo($cheque);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The last step of the cheque was undone.')]);

        return to_route('admin.banking.cheques.show', $cheque);
    }

    /**
     * The permission is checked by the route, before the format is validated.
     */
    public function export(ExportRequest $request): SymfonyResponse
    {
        return ChequesExporter::from($this->query()->with(['contact', 'bankAccount.currency', 'currency']))->download($request->exportFormat());
    }

    /**
     * The filtered and sorted register, shared by the page and its exports.
     * By default the nearest due dates come first.
     *
     * @return QueryBuilder<Cheque>
     */
    protected function query(): QueryBuilder
    {
        return QueryBuilder::for(Cheque::class)
            ->allowedFilters(
                SearchFilter::on('number', 'drawee_bank', 'description'),
                AllowedFilter::exact('direction'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('contact', 'contact_id'),
                AllowedFilter::exact('account', 'bank_account_id'),
                AllowedFilter::callback('open', fn (Builder $query, mixed $open) => $query->when(filter_var($open, FILTER_VALIDATE_BOOLEAN), fn (Builder $openOnly) => $openOnly->open()))->delimiter(''),
                DateFilter::from('due_from', 'due_date'),
                DateFilter::to('due_to', 'due_date'),
            )
            ->defaultSort('due_date', 'id')
            ->allowedSorts('due_date', 'issue_date', 'number', 'amount');
    }

    /**
     * Base-currency totals of the cheques that still wait for the bank, and
     * of those due in the next seven days.
     *
     * @return array{in_hand: string, under_collection: string, issued: string, due_this_week: string, overdue: int}
     */
    protected function summary(): array
    {
        $open = Cheque::query()->open()->get(['direction', 'status', 'amount', 'exchange_rate', 'due_date']);
        $total = fn (iterable $cheques): string => Decimal::round(Decimal::sum(collect($cheques)->map(fn (Cheque $cheque): string => Decimal::multiply($cheque->amount, $cheque->exchange_rate))));
        $weekEnd = now()->addDays(7)->endOfDay();

        return [
            'in_hand' => $total($open->where('status', ChequeStatus::InHand)),
            'under_collection' => $total($open->where('status', ChequeStatus::UnderCollection)),
            'issued' => $total($open->where('status', ChequeStatus::Issued)),
            'due_this_week' => $total($open->filter(fn (Cheque $cheque): bool => $cheque->due_date->lessThanOrEqualTo($weekEnd))),
            'overdue' => $open->filter(fn (Cheque $cheque): bool => $cheque->due_date->lessThan(now()->startOfDay()))->count(),
        ];
    }
}
