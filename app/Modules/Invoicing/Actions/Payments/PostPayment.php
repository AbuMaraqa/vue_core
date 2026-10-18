<?php

namespace App\Modules\Invoicing\Actions\Payments;

use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Posting\JournalEntryBuilder;
use App\Modules\Accounting\Posting\Ledger;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Accounting\Support\DefaultAccounts;
use App\Modules\Accounting\Support\DocumentNumbers;
use App\Modules\Banking\Data\ChequeData;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Banking\Support\ChequeRegister;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Invoicing\Data\PaymentData;
use App\Modules\Invoicing\Enums\PaymentMethod;
use App\Modules\Invoicing\Enums\PaymentType;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Payment;
use App\Modules\Invoicing\Support\Allocator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Record a receipt or payment, post it and allocate it:
 *
 *   receipt: Dr bank (or cheques in hand), Cr customer receivable
 *   payment: Dr vendor payable, Cr bank (or cheques payable)
 *
 * A cheque is entered in the cheque register with this entry, and follows
 * its own life from there; if it bounces, the payment stops settling the
 * invoices.
 */
class PostPayment
{
    public function __construct(
        protected Ledger $ledger,
        protected Currencies $currencies,
        protected DocumentNumbers $numbers,
        protected DefaultAccounts $defaults,
        protected ChequeRegister $cheques,
        protected Allocator $allocator,
    ) {}

    public function handle(PaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $this->ledger->ensureDateIsOpen($data->date);

            $contact = Contact::query()->findOrFail($data->contact_id);
            $currency = Currency::query()->findOrFail($data->currency_id);
            $isCheque = $data->method === PaymentMethod::Cheque;
            $bankAccount = $data->bank_account_id !== null ? BankAccount::query()->find($data->bank_account_id) : null;

            if ($bankAccount === null && (! $isCheque || $data->type === PaymentType::Payment)) {
                throw new AccountingException(__('Choose the bank or cash account.'));
            }

            $userId = Auth::id();
            $payment = Payment::query()->create([
                'type' => $data->type,
                'number' => $this->numbers->next($data->type->documentType(), $data->date),
                'contact_id' => $contact->id,
                'date' => $data->date,
                'method' => $data->method,
                'bank_account_id' => $bankAccount?->id,
                'amount' => Decimal::round($data->amount, $currency->decimal_places),
                'currency_id' => $currency->id,
                'exchange_rate' => $this->currencies->isBase($currency)
                    ? '1'
                    : (filled($data->exchange_rate) ? (string) $data->exchange_rate : $this->currencies->rate($currency, $data->date)),
                'reference' => $data->reference,
                'description' => $data->description,
                'status' => DocumentStatus::Posted,
                'created_by' => is_int($userId) ? $userId : null,
            ]);

            $contactAccount = $data->type === PaymentType::Receipt ? $contact->receivableAccountId() : $contact->payableAccountId();
            $moneyAccount = match (true) {
                ! $isCheque => $bankAccount->account_id ?? throw new AccountingException(__('Choose the bank or cash account.')),
                $data->type === PaymentType::Receipt => $this->defaults->id(AccountRole::ChequesInHand),
                default => $this->defaults->id(AccountRole::ChequesPayable),
            };

            $entry = JournalEntryBuilder::make($data->date, $data->description ?? "{$data->type->label()} {$payment->number} — {$contact->name}")
                ->currency($currency, (string) $payment->exchange_rate)
                ->reference($data->reference ?? $payment->number)
                ->source($payment);

            if ($data->type === PaymentType::Receipt) {
                $entry->debit($moneyAccount, $payment->amount, partner: $isCheque ? $contact : null)->credit($contactAccount, $payment->amount, partner: $contact);
            } else {
                $entry->debit($contactAccount, $payment->amount, partner: $contact)->credit($moneyAccount, $payment->amount, partner: $isCheque ? $contact : null);
            }

            $posted = $entry->post();

            if ($isCheque) {
                $cheque = $this->cheques->record(new ChequeData(
                    direction: $data->type->chequeDirection(),
                    amount: $payment->amount,
                    currency_id: $currency->id,
                    issue_date: $data->date,
                    due_date: $data->cheque_due_date ?? $data->date,
                    number: $data->cheque_number,
                    contact_id: $contact->id,
                    exchange_rate: (string) $payment->exchange_rate,
                    drawee_bank: $data->drawee_bank,
                    bank_account_id: $data->type === PaymentType::Payment ? $bankAccount?->id : null,
                    cheque_book_id: $data->cheque_book_id,
                    counter_account_id: $contactAccount,
                    description: $data->description,
                ), $posted, $payment);

                $payment->update(['cheque_id' => $cheque->id]);
            }

            foreach ($data->allocations as $allocation) {
                if (Decimal::isPositive($allocation['amount'])) {
                    $this->allocator->allocatePayment($payment, Invoice::query()->findOrFail($allocation['invoice_id']), $allocation['amount'], $data->date);
                }
            }

            return $payment;
        });
    }
}
