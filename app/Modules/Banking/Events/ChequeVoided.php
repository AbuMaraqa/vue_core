<?php

namespace App\Modules\Banking\Events;

use App\Modules\Banking\Models\Cheque;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A cheque stopped settling what it paid: it bounced, was returned to the
 * customer or was cancelled. Its entry has already put the amount back on
 * the contact; Invoicing frees the invoices the cheque's payment settled.
 */
class ChequeVoided
{
    use Dispatchable;

    public function __construct(public Cheque $cheque) {}
}
