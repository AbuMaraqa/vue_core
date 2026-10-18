<?php

namespace App\Modules\Contacts\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Reports\ContactStatement;
use Carbon\CarbonInterface;

/**
 * The printed statement of account of a customer or vendor for a period:
 * the opening balance, every posted movement with the running balance, and
 * the closing balance, in the base currency.
 */
class ContactStatementDocument extends FinancialDocument
{
    public function __construct(
        protected Contact $contact,
        protected CarbonInterface $from,
        protected CarbonInterface $to,
    ) {}

    public function title(): string
    {
        return match (true) {
            $this->contact->is_customer && ! $this->contact->is_vendor => __('Customer statement'),
            $this->contact->is_vendor && ! $this->contact->is_customer => __('Vendor statement'),
            default => __('Statement of Account'),
        };
    }

    public function number(): ?string
    {
        return $this->contact->code;
    }

    public function details(): array
    {
        return [
            __('From') => self::date($this->from),
            __('To') => self::date($this->to),
            __('Currency') => app(Currencies::class)->base()->code,
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Approved by')];
    }

    protected function view(): string
    {
        return 'contacts::print.statement';
    }

    protected function data(): array
    {
        return [
            'contact' => $this->contact,
            'statement' => app(ContactStatement::class)->generate($this->contact, $this->from, $this->to),
            'currency' => app(Currencies::class)->base(),
        ];
    }
}
