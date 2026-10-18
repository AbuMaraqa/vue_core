<?php

namespace App\Modules\Banking\Events;

use App\Modules\Banking\Models\Cheque;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A cheque was returned unpaid by the bank. Its entry has already restored
 * the contact's balance; other modules react here, e.g. Invoicing reopens
 * the invoices the cheque paid.
 */
class ChequeBounced
{
    use Dispatchable;

    public function __construct(public Cheque $cheque) {}
}
