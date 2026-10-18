<?php

namespace App\Modules\Invoicing\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Invoicing\Models\Invoice;

/**
 * The printed sales invoice, credit note, purchase bill or debit note, with
 * the tax numbers of both parties and the amount due in words.
 */
class InvoiceDocument extends FinancialDocument
{
    public function __construct(protected Invoice $invoice)
    {
        $invoice->loadMissing(['contact', 'currency', 'lines.item.unit']);
    }

    public function title(): string
    {
        return $this->invoice->type->label();
    }

    public function number(): ?string
    {
        return $this->invoice->number;
    }

    public function status(): ?array
    {
        return $this->documentStatus($this->invoice->status);
    }

    public function details(): array
    {
        return [
            __('Date') => self::date($this->invoice->date),
            __('Due date') => self::date($this->invoice->due_date),
            __('Currency') => $this->invoice->currency->code,
            __('Reference') => $this->invoice->reference,
        ];
    }

    public function signatures(): array
    {
        return $this->invoice->type->isSales()
            ? [__('Prepared by'), __('Received by')]
            : [__('Prepared by'), __('Approved by')];
    }

    public function filename(): string
    {
        return str_replace('/', '-', $this->invoice->label()).'.pdf';
    }

    protected function view(): string
    {
        return 'invoicing::print.invoice';
    }

    protected function data(): array
    {
        return ['invoice' => $this->invoice];
    }
}
