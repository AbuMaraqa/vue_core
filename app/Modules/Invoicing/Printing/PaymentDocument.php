<?php

namespace App\Modules\Invoicing\Printing;

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Invoicing\Enums\PaymentType;
use App\Modules\Invoicing\Models\Payment;

/**
 * The printed receipt voucher of a customer receipt, or the payment voucher
 * of a vendor payment: the amount in figures and in words, how it was paid,
 * and the invoices it settled.
 */
class PaymentDocument extends FinancialDocument
{
    public function __construct(protected Payment $payment)
    {
        $payment->loadMissing(['contact', 'currency', 'bankAccount', 'cheque', 'allocations.invoice']);
    }

    public function title(): string
    {
        return $this->isReceipt() ? __('Receipt voucher') : __('Payment voucher');
    }

    public function number(): ?string
    {
        return $this->payment->number;
    }

    /**
     * Drafts and cancelled payments are marked, and so is a posted payment
     * whose cheque bounced or was cancelled, since it no longer settles anything.
     */
    public function status(): ?array
    {
        if ($this->payment->status === DocumentStatus::Posted && ! $this->payment->isEffective()) {
            return ['label' => __('Not effective'), 'tone' => 'danger'];
        }

        return $this->documentStatus($this->payment->status);
    }

    public function details(): array
    {
        $payment = $this->payment;
        $exchangeRate = rtrim(rtrim((string) $payment->exchange_rate, '0'), '.');

        return [
            __('Date') => self::date($payment->date),
            __('Method') => $payment->method->label(),
            ($this->isReceipt() ? __('Received into') : __('Paid from')) => $payment->bankAccount?->name,
            __('Currency') => $payment->currency->code,
            __('Exchange rate') => $exchangeRate !== '1' ? $exchangeRate : null,
            __('Reference') => $payment->reference,
            __('Cheque number') => $payment->cheque?->number,
            __('Due date') => self::date($payment->cheque?->due_date),
            __('Drawee bank') => $payment->cheque?->drawee_bank,
        ];
    }

    public function signatures(): array
    {
        return $this->isReceipt()
            ? [__('Received by'), __('Accountant')]
            : [__('Prepared by'), __('Approved by'), __('Received by')];
    }

    protected function view(): string
    {
        return 'invoicing::print.payment';
    }

    protected function data(): array
    {
        return ['payment' => $this->payment, 'isReceipt' => $this->isReceipt()];
    }

    protected function isReceipt(): bool
    {
        return $this->payment->type === PaymentType::Receipt;
    }
}
