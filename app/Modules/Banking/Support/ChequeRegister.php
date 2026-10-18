<?php

namespace App\Modules\Banking\Support;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Banking\Data\ChequeData;
use App\Modules\Banking\Enums\ChequeAction;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Enums\ChequeStatus;
use App\Modules\Banking\Events\ChequeBounced;
use App\Modules\Banking\Events\ChequeVoided;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Banking\Models\ChequeBook;
use App\Modules\Banking\Models\ChequeEvent;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The life of every cheque, and its entries.
 *
 * In each status the value of a cheque sits in one account:
 *
 * | Status           | Received cheque                 | Issued cheque    |
 * | ---------------- | ------------------------------- | ---------------- |
 * | in hand          | cheques in hand                 |                  |
 * | under collection | cheques under collection        |                  |
 * | collected        | the bank account                |                  |
 * | issued           |                                 | cheques payable  |
 * | cleared          |                                 | the bank account |
 * | bounced/returned | the counter account (customer)  | counter (vendor) |
 * | cancelled        |                                 | counter (vendor) |
 * | endorsed         | the payable of the endorsee     |                  |
 *
 * So every action posts one entry that moves the value from the old place
 * to the new one (none when both are the same), in the cheque's currency at
 * the cheque's rate. Lines carry the contact as partner, so the contact's
 * statement shows the cheque.
 */
class ChequeRegister
{
    public function __construct(
        protected Ledger $ledger,
        protected DefaultAccounts $defaults,
        protected Currencies $currencies,
    ) {}

    /**
     * Receive a cheque and post it: Dr cheques in hand, Cr the counter
     * account (the customer's receivable by default).
     */
    public function receive(ChequeData $data): Cheque
    {
        return $this->createAndPost($data, ChequeDirection::Incoming);
    }

    /**
     * Write a cheque and post it: Dr the counter account (the vendor's
     * payable by default), Cr cheques payable.
     */
    public function issue(ChequeData $data): Cheque
    {
        return $this->createAndPost($data, ChequeDirection::Outgoing);
    }

    /**
     * Register a cheque whose entry another document posted, e.g. a customer
     * receipt paid by cheque. The entry must already move the value to
     * cheques in hand (received) or cheques payable (issued).
     */
    public function record(ChequeData $data, ?JournalEntry $entry = null, ?Model $source = null): Cheque
    {
        return DB::transaction(function () use ($data, $entry, $source): Cheque {
            $cheque = $this->create($data, $source);

            $this->log($cheque, $cheque->direction === ChequeDirection::Incoming ? ChequeAction::Receive : ChequeAction::Issue, null, $cheque->status, $data->issue_date, $entry);

            return $cheque;
        });
    }

    /**
     * Move a cheque on: deposit, collect, bounce, endorse, clear...
     */
    public function perform(
        Cheque $cheque,
        ChequeAction $action,
        string $date,
        ?int $bankAccountId = null,
        ?int $endorseToContactId = null,
        ?string $note = null,
    ): Cheque {
        return DB::transaction(function () use ($cheque, $action, $date, $bankAccountId, $endorseToContactId, $note): Cheque {
            $cheque = Cheque::query()->lockForUpdate()->with(['currency', 'contact', 'bankAccount'])->findOrFail($cheque->id);
            $from = $cheque->status;
            $to = $action->transitions($cheque->direction)[$from->value] ?? throw new AccountingException(__('A cheque that is :status cannot be changed with ":action".', [
                'status' => $from->label(),
                'action' => $action->label(),
            ]));

            $origin = $this->location($cheque, $from);

            if ($action->needsBankAccount() && ($action === ChequeAction::Deposit || $from === ChequeStatus::InHand)) {
                $cheque->bank_account_id = $this->bankAccountFor($cheque, $bankAccountId)->id;
                $cheque->setRelation('bankAccount', BankAccount::query()->find($cheque->bank_account_id));
            }

            if ($action === ChequeAction::Endorse) {
                $endorsee = Contact::query()->find($endorseToContactId) ?? throw new AccountingException(__('Choose who the cheque is endorsed to.'));
                $cheque->endorsed_to_contact_id = $endorsee->id;
                $cheque->setRelation('endorsedTo', $endorsee);
            }

            $entry = $this->move($cheque, $origin, $this->location($cheque, $to), $date, $action);

            $cheque->status = $to;
            $cheque->save();

            $this->log($cheque, $action, $from, $to, $date, $entry, $note);

            if ($action === ChequeAction::Bounce) {
                ChequeBounced::dispatch($cheque);
            }

            if ($to->isVoid() && ! $from->isVoid()) {
                ChequeVoided::dispatch($cheque);
            }

            return $cheque;
        });
    }

    /**
     * Undo the last step of a cheque, e.g. a deposit recorded by mistake:
     * its entry is reversed and the cheque returns to its previous status.
     * Receiving or issuing is undone by returning or cancelling the cheque.
     */
    public function undo(Cheque $cheque): Cheque
    {
        return DB::transaction(function () use ($cheque): Cheque {
            $cheque = Cheque::query()->lockForUpdate()->findOrFail($cheque->id);

            $last = $cheque->events()->where('is_undone', false)->reorder()->orderByDesc('id')->first();

            if ($last === null || $last->from_status === null) {
                throw new AccountingException(__('There is nothing to undo. Return or cancel the cheque instead.'));
            }

            if ($last->journalEntry !== null) {
                $this->ledger->reverse($last->journalEntry, $this->ledger->openDateFor($last->date), __('Undo of cheque :number', ['number' => $cheque->number]));
            }

            $cheque->status = $last->from_status;

            if ($last->action === ChequeAction::Endorse) {
                $cheque->endorsed_to_contact_id = null;
            }

            $cheque->save();
            $last->update(['is_undone' => true]);

            return $cheque;
        });
    }

    protected function createAndPost(ChequeData $data, ChequeDirection $direction): Cheque
    {
        return DB::transaction(function () use ($data, $direction): Cheque {
            $this->ledger->ensureDateIsOpen($data->issue_date);

            $cheque = $this->create(ChequeData::from([...$data->toArray(), 'direction' => $direction->value]));
            $cheque->load(['currency', 'contact', 'bankAccount']);

            $entry = $this->move(
                $cheque,
                [$cheque->counter_account_id, $cheque->contact],
                $this->location($cheque, $cheque->status),
                $data->issue_date,
                $direction === ChequeDirection::Incoming ? ChequeAction::Receive : ChequeAction::Issue,
            );

            $this->log($cheque, $direction === ChequeDirection::Incoming ? ChequeAction::Receive : ChequeAction::Issue, null, $cheque->status, $data->issue_date, $entry);

            return $cheque;
        });
    }

    protected function create(ChequeData $data, ?Model $source = null): Cheque
    {
        $contact = $data->contact_id !== null ? Contact::query()->findOrFail($data->contact_id) : null;
        $incoming = $data->direction === ChequeDirection::Incoming;

        $counterAccountId = $data->counter_account_id
            ?? ($contact === null ? null : ($incoming ? $contact->receivableAccountId() : $contact->payableAccountId()))
            ?? throw new AccountingException(__('Choose the contact or the account the cheque settles.'));

        $bankAccount = null;

        if (! $incoming) {
            $bankAccount = BankAccount::query()->find($data->bank_account_id)
                ?? throw new AccountingException(__('Choose the bank account the cheque is drawn on.'));

            if ($bankAccount->currency_id !== $data->currency_id) {
                throw new AccountingException(__('The cheque must be in the currency of :account.', ['account' => $bankAccount->name]));
            }
        }

        $userId = Auth::id();

        return Cheque::query()->create([
            'direction' => $data->direction,
            'number' => $data->number ?? $this->numberFromBook($data, $bankAccount),
            'status' => $data->direction->initialStatus(),
            'contact_id' => $contact?->id,
            'bank_account_id' => $bankAccount?->id,
            'cheque_book_id' => $data->cheque_book_id,
            'drawee_bank' => $data->drawee_bank,
            'amount' => Decimal::round($data->amount),
            'currency_id' => $data->currency_id,
            'exchange_rate' => $this->currencies->isBase($data->currency_id)
                ? '1'
                : ($data->exchange_rate ?? $this->currencies->rate($data->currency_id, $data->issue_date)),
            'issue_date' => $data->issue_date,
            'due_date' => $data->due_date,
            'counter_account_id' => $counterAccountId,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'description' => $data->description,
            'created_by' => is_int($userId) ? $userId : null,
        ]);
    }

    /**
     * Take the next number of the cheque book.
     */
    protected function numberFromBook(ChequeData $data, ?BankAccount $bankAccount): string
    {
        $book = ChequeBook::query()->lockForUpdate()->find($data->cheque_book_id);

        if ($book === null || $bankAccount === null || $book->bank_account_id !== $bankAccount->id) {
            throw new AccountingException(__('Enter the cheque number or choose a cheque book of the bank account.'));
        }

        if ($book->isUsedUp()) {
            throw new AccountingException(__('The cheque book is used up.'));
        }

        $book->increment('next_number');

        return (string) ($book->next_number - 1);
    }

    /**
     * The account (and partner) holding the cheque's value in a status.
     *
     * @return array{0: int, 1: Model|null}
     */
    protected function location(Cheque $cheque, ChequeStatus $status): array
    {
        return match ($status) {
            ChequeStatus::InHand => [$this->defaults->id(AccountRole::ChequesInHand), $cheque->contact],
            ChequeStatus::UnderCollection => [$this->defaults->id(AccountRole::ChequesUnderCollection), $cheque->contact],
            ChequeStatus::Issued => [$this->defaults->id(AccountRole::ChequesPayable), $cheque->contact],
            ChequeStatus::Collected, ChequeStatus::Cleared => [
                $cheque->bankAccount->account_id ?? throw new AccountingException(__('Choose the bank account.')),
                null,
            ],
            ChequeStatus::Endorsed => [
                $cheque->endorsedTo?->payableAccountId() ?? throw new AccountingException(__('Choose who the cheque is endorsed to.')),
                $cheque->endorsedTo,
            ],
            ChequeStatus::Bounced, ChequeStatus::Returned, ChequeStatus::Cancelled => [$cheque->counter_account_id, $cheque->contact],
        };
    }

    /**
     * Post the move of the cheque's value. A received cheque's value is a
     * debit, so it is debited where it goes; an issued cheque's value is a
     * credit, so it is credited where it goes.
     *
     * @param  array{0: int, 1: Model|null}  $origin
     * @param  array{0: int, 1: Model|null}  $destination
     */
    protected function move(Cheque $cheque, array $origin, array $destination, string $date, ChequeAction $action): ?JournalEntry
    {
        if ($origin[0] === $destination[0] && $origin[1]?->getKey() === $destination[1]?->getKey()) {
            return null;
        }

        [$debit, $credit] = $cheque->direction === ChequeDirection::Incoming ? [$destination, $origin] : [$origin, $destination];

        $description = __('Cheque :number: :action', ['number' => $cheque->number, 'action' => $action->label()]);

        return JournalEntryBuilder::make($date, $description)
            ->currency($cheque->currency, (string) $cheque->exchange_rate)
            ->reference($cheque->number)
            ->source($cheque)
            ->debit($debit[0], $cheque->amount, partner: $debit[1])
            ->credit($credit[0], $cheque->amount, partner: $credit[1])
            ->post();
    }

    protected function bankAccountFor(Cheque $cheque, ?int $bankAccountId): BankAccount
    {
        $bankAccount = BankAccount::query()->where('is_active', true)->find($bankAccountId ?? $cheque->bank_account_id)
            ?? throw new AccountingException(__('Choose the bank account.'));

        if ($bankAccount->currency_id !== $cheque->currency_id) {
            throw new AccountingException(__('The cheque must be in the currency of :account.', ['account' => $bankAccount->name]));
        }

        return $bankAccount;
    }

    protected function log(Cheque $cheque, ChequeAction $action, ?ChequeStatus $from, ChequeStatus $to, string $date, ?JournalEntry $entry, ?string $note = null): ChequeEvent
    {
        $userId = Auth::id();

        return $cheque->events()->create([
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'date' => $date,
            'journal_entry_id' => $entry?->id,
            'note' => $note,
            'created_by' => is_int($userId) ? $userId : null,
        ]);
    }
}
