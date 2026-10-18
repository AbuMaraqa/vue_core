<?php

namespace App\Modules\Banking\Exports;

use App\Modules\Banking\Models\Cheque;
use App\Modules\Foundation\Exports\Exporter;

/**
 * The cheque register, e.g. the cheques due this month for the bank.
 *
 * @extends Exporter<Cheque>
 */
class ChequesExporter extends Exporter
{
    public function title(): string
    {
        return 'Cheques';
    }

    public function columns(): array
    {
        return [
            'Type' => fn (Cheque $cheque): string => $cheque->direction->label(),
            'Cheque number' => fn (Cheque $cheque): string => $cheque->number,
            'Contact' => fn (Cheque $cheque): ?string => $cheque->contact?->name,
            'Bank' => fn (Cheque $cheque): ?string => $cheque->drawee_bank ?? $cheque->bankAccount?->label(),
            'Cheque date' => fn (Cheque $cheque): string => $cheque->issue_date->toDateString(),
            'Due date' => fn (Cheque $cheque): string => $cheque->due_date->toDateString(),
            'Amount' => fn (Cheque $cheque): string => (string) $cheque->amount,
            'Currency' => fn (Cheque $cheque): string => $cheque->currency->code,
            'Status' => fn (Cheque $cheque): string => $cheque->status->label(),
        ];
    }
}
