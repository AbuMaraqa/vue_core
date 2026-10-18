<?php

namespace App\Modules\Invoicing\Actions\Invoices;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Invoicing\Actions\Payments\PostPayment;
use App\Modules\Invoicing\Data\PaymentData;
use App\Modules\Invoicing\Enums\PaymentMethod;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Payment;

/**
 * Settle a posted sales invoice or purchase bill at once: record a receipt
 * (or vendor payment) for what is due, from a cash box or bank account, on
 * the invoice's date and at its exchange rate, allocated to it. Used when
 * the customer pays on the spot.
 */
class PayInvoiceInFull
{
    public function __construct(protected PostPayment $postPayment) {}

    public function handle(Invoice $invoice, BankAccount $bankAccount): Payment
    {
        $paymentType = $invoice->type->paymentType();

        if ($paymentType === null || $invoice->status !== DocumentStatus::Posted) {
            throw new AccountingException(__(':document cannot be settled by this payment.', ['document' => $invoice->label()]));
        }

        if ($bankAccount->currency_id !== $invoice->currency_id) {
            throw new AccountingException(__('Choose an account in the currency of the document.'));
        }

        $amount = $invoice->amountDue();

        return $this->postPayment->handle(new PaymentData(
            type: $paymentType,
            contact_id: $invoice->contact_id,
            date: $invoice->date->toDateString(),
            method: $bankAccount->type === BankAccountType::Cash ? PaymentMethod::Cash : PaymentMethod::BankTransfer,
            amount: $amount,
            currency_id: $invoice->currency_id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => $amount]],
            bank_account_id: $bankAccount->id,
            exchange_rate: (string) $invoice->exchange_rate,
            reference: $invoice->number,
        ));
    }
}
