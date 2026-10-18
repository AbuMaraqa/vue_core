<?php

namespace App\Modules\Invoicing\Support;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Invoicing\Models\Allocation;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Payment;
use Brick\Math\BigNumber;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Settle invoices with payments, or with credit and debit notes, and undo
 * it. Both sides must belong to the same contact and currency.
 *
 * When the two were made at different exchange rates, the contact's account
 * is left with a base-currency difference after the amounts match; it is
 * the realized exchange gain or loss, posted with the allocation.
 */
class Allocator
{
    public function __construct(
        protected Ledger $ledger,
        protected DefaultAccounts $defaults,
    ) {}

    public function allocatePayment(Payment $payment, Invoice $invoice, BigNumber|string $amount, CarbonInterface|string|null $date = null): Allocation
    {
        return DB::transaction(function () use ($payment, $invoice, $amount, $date): Allocation {
            $payment = Payment::query()->lockForUpdate()->with(['allocations', 'cheque'])->findOrFail($payment->id);
            $invoice = $this->lockedOpenInvoice($invoice);
            $amount = $this->amount($amount);

            if (! $payment->isEffective()) {
                throw new AccountingException(__('The payment :number no longer settles invoices.', ['number' => $payment->number]));
            }

            if ($invoice->type !== $payment->type->invoiceType()) {
                throw new AccountingException(__(':document cannot be settled by this payment.', ['document' => $invoice->label()]));
            }

            $this->ensureSameContactAndCurrency($payment->contact_id, $payment->currency_id, $invoice);

            if ($amount->isGreaterThan($payment->unallocatedAmount())) {
                throw new AccountingException(__('Only :amount of the payment is left to allocate.', ['amount' => $payment->unallocatedAmount()]));
            }

            return $this->settle($invoice, $amount, (string) $payment->exchange_rate, $date ?? $payment->date, ['payment_id' => $payment->id]);
        });
    }

    /**
     * Apply a credit note to a sales invoice, or a debit note to a bill.
     */
    public function applyNote(Invoice $note, Invoice $invoice, BigNumber|string $amount, CarbonInterface|string|null $date = null): Allocation
    {
        return DB::transaction(function () use ($note, $invoice, $amount, $date): Allocation {
            $note = Invoice::query()->lockForUpdate()->findOrFail($note->id);
            $invoice = $this->lockedOpenInvoice($invoice);
            $amount = $this->amount($amount);

            if (! $note->type->isReturn() || $note->status !== DocumentStatus::Posted || $invoice->type !== $note->type->counterpart()) {
                throw new AccountingException(__(':note cannot be applied to :document.', ['note' => $note->label(), 'document' => $invoice->label()]));
            }

            $this->ensureSameContactAndCurrency($note->contact_id, $note->currency_id, $invoice);

            if ($amount->isGreaterThan($note->amountDue())) {
                throw new AccountingException(__('Only :amount of :note is left to apply.', ['amount' => $note->amountDue(), 'note' => $note->label()]));
            }

            $allocation = $this->settle($invoice, $amount, (string) $note->exchange_rate, $date ?? now(), ['credit_note_id' => $note->id]);

            $note->update(['paid_amount' => Decimal::round(Decimal::of($note->paid_amount)->plus($amount))]);

            return $allocation;
        });
    }

    /**
     * Remove an allocation: the invoice (and note) are open again and the
     * exchange difference, if any, is reversed.
     */
    public function remove(Allocation $allocation): void
    {
        DB::transaction(function () use ($allocation): void {
            $allocation = Allocation::query()->lockForUpdate()->with(['journalEntry', 'invoice', 'creditNote'])->findOrFail($allocation->id);

            if ($allocation->journalEntry !== null && $allocation->journalEntry->reversedBy()->doesntExist()) {
                $this->ledger->reverse($allocation->journalEntry, $this->ledger->openDateFor($allocation->date), __('Removal of an allocation to :number', ['number' => $allocation->invoice->number]));
            }

            foreach (array_filter([$allocation->invoice, $allocation->creditNote]) as $document) {
                $document->update(['paid_amount' => Decimal::round(Decimal::of($document->paid_amount)->minus($allocation->amount))]);
            }

            $allocation->delete();
        });
    }

    /**
     * @param  array<string, int>  $settledBy
     */
    protected function settle(Invoice $invoice, BigNumber $amount, string $settlingRate, CarbonInterface|string $date, array $settledBy): Allocation
    {
        if ($amount->isGreaterThan($invoice->amountDue())) {
            throw new AccountingException(__('Only :amount of :document is due.', ['amount' => $invoice->amountDue(), 'document' => $invoice->label()]));
        }

        $userId = Auth::id();

        $allocation = Allocation::query()->create([
            'invoice_id' => $invoice->id,
            ...$settledBy,
            'amount' => Decimal::round($amount),
            'date' => $date,
            'journal_entry_id' => $this->postExchangeDifference($invoice, $amount, $settlingRate, $date)?->id,
            'created_by' => is_int($userId) ? $userId : null,
        ]);

        $invoice->update(['paid_amount' => Decimal::round(Decimal::of($invoice->paid_amount)->plus($amount))]);

        return $allocation;
    }

    /**
     * The base-currency difference left on the contact's account. For a
     * sales invoice, the receivable was debited at the invoice's rate and
     * credited at the payment's; what remains is a loss when positive.
     */
    protected function postExchangeDifference(Invoice $invoice, BigNumber $amount, string $settlingRate, CarbonInterface|string $date): ?JournalEntry
    {
        $difference = Decimal::of(Decimal::multiply($amount, (string) $invoice->exchange_rate))
            ->minus(Decimal::multiply($amount, $settlingRate))
            ->multipliedBy($invoice->type->debitsContact() ? 1 : -1);

        if ($difference->isZero()) {
            return null;
        }

        $contact = $invoice->contact;
        $contactAccount = $invoice->type->isSales() ? $contact->receivableAccountId() : $contact->payableAccountId();
        $entry = JournalEntryBuilder::make($this->ledger->openDateFor($date), __('Exchange difference on :number', ['number' => $invoice->number]))
            ->reference($invoice->number)
            ->source($invoice);

        if ($difference->isPositive()) {
            $entry->debit($this->defaults->id(AccountRole::ExchangeLoss), $difference)->credit($contactAccount, $difference, partner: $contact);
        } else {
            $entry->debit($contactAccount, $difference->abs(), partner: $contact)->credit($this->defaults->id(AccountRole::ExchangeGain), $difference->abs());
        }

        return $entry->post();
    }

    protected function lockedOpenInvoice(Invoice $invoice): Invoice
    {
        $invoice = Invoice::query()->lockForUpdate()->with('contact')->findOrFail($invoice->id);

        if ($invoice->status !== DocumentStatus::Posted || $invoice->type->isReturn()) {
            throw new AccountingException(__(':document cannot be settled.', ['document' => $invoice->label()]));
        }

        return $invoice;
    }

    protected function ensureSameContactAndCurrency(int $contactId, int $currencyId, Invoice $invoice): void
    {
        if ($contactId !== $invoice->contact_id) {
            throw new AccountingException(__(':document belongs to another contact.', ['document' => $invoice->label()]));
        }

        if ($currencyId !== $invoice->currency_id) {
            throw new AccountingException(__(':document is in another currency.', ['document' => $invoice->label()]));
        }
    }

    protected function amount(BigNumber|string $amount): BigNumber
    {
        $amount = Decimal::of($amount);

        if (! $amount->isPositive()) {
            throw new AccountingException(__('Enter an amount to allocate.'));
        }

        return $amount;
    }
}
