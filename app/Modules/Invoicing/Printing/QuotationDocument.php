<?php

namespace App\Modules\Invoicing\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Invoicing\Enums\QuotationStatus;
use App\Modules\Invoicing\Models\Quotation;

/**
 * The printed quotation given to the customer, with its validity date and
 * the total in words.
 */
class QuotationDocument extends FinancialDocument
{
    public function __construct(protected Quotation $quotation)
    {
        $quotation->loadMissing(['contact', 'currency', 'lines.item.unit']);
    }

    public function title(): string
    {
        return __('Sales quotation');
    }

    public function number(): ?string
    {
        return $this->quotation->number;
    }

    /**
     * Rejected and expired quotations are marked, so an old printout is not
     * taken for a valid offer.
     */
    public function status(): ?array
    {
        $status = $this->quotation->displayStatus();

        return match ($status) {
            QuotationStatus::Rejected => ['label' => $status->label(), 'tone' => 'danger'],
            QuotationStatus::Expired => ['label' => $status->label(), 'tone' => 'warning'],
            default => null,
        };
    }

    public function details(): array
    {
        return [
            __('Date') => self::date($this->quotation->date),
            __('Valid until') => self::date($this->quotation->valid_until),
            __('Currency') => $this->quotation->currency->code,
            __('Reference') => $this->quotation->reference,
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Customer approval')];
    }

    public function filename(): string
    {
        return str_replace('/', '-', $this->quotation->number).'.pdf';
    }

    protected function view(): string
    {
        return 'invoicing::print.quotation';
    }

    protected function data(): array
    {
        return ['quotation' => $this->quotation];
    }
}
