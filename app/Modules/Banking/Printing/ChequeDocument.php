<?php

namespace App\Modules\Banking\Printing;

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Banking\Enums\ChequeDirection;
use App\Modules\Banking\Models\Cheque;

/**
 * The printed record of a received or issued cheque (not the cheque leaf):
 * the contact, the amount in words, the due date, where the cheque is now
 * and every step of its life.
 */
class ChequeDocument extends FinancialDocument
{
    public function __construct(protected Cheque $cheque)
    {
        $cheque->loadMissing(['contact', 'bankAccount.currency', 'currency', 'counterAccount', 'endorsedTo', 'events']);
    }

    public function title(): string
    {
        return $this->cheque->direction->label();
    }

    public function number(): ?string
    {
        return $this->cheque->number;
    }

    /**
     * The cheque's place in its life is always shown: a printout of a bounced
     * or cancelled cheque must never pass for a good one.
     */
    public function status(): ?array
    {
        $status = $this->cheque->status;

        return [
            'label' => $status->label(),
            'tone' => match ($status->color()) {
                'success' => 'success',
                'warning' => 'warning',
                'error' => 'danger',
                default => 'neutral',
            },
        ];
    }

    public function details(): array
    {
        $isForeign = (float) $this->cheque->exchange_rate !== 1.0;

        return [
            __('Cheque date') => self::date($this->cheque->issue_date),
            __('Due date') => self::date($this->cheque->due_date),
            __('Currency') => $this->cheque->currency->code,
            __('Exchange rate') => $isForeign ? self::quantity($this->cheque->exchange_rate) : null,
            __('Drawee bank') => $this->cheque->drawee_bank,
            __('Bank account') => $this->cheque->bankAccount?->label(),
            __('Counter account') => $this->cheque->counterAccount->label(),
        ];
    }

    public function signatures(): array
    {
        return $this->cheque->direction === ChequeDirection::Incoming
            ? [__('Received by'), __('Accountant')]
            : [__('Prepared by'), __('Approved by'), __('Received by')];
    }

    public function filename(): string
    {
        return 'cheque-'.str_replace('/', '-', $this->cheque->number).'.pdf';
    }

    protected function view(): string
    {
        return 'banking::print.cheque';
    }

    protected function data(): array
    {
        return ['cheque' => $this->cheque];
    }
}
