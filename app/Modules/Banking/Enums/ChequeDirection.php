<?php

namespace App\Modules\Banking\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Cheques received from customers, or written to vendors.
 */
enum ChequeDirection: string
{
    use EnumOptions;

    case Incoming = 'incoming';
    case Outgoing = 'outgoing';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => __('Received cheque'),
            self::Outgoing => __('Issued cheque'),
        };
    }

    /**
     * The status of a new cheque.
     */
    public function initialStatus(): ChequeStatus
    {
        return match ($this) {
            self::Incoming => ChequeStatus::InHand,
            self::Outgoing => ChequeStatus::Issued,
        };
    }
}
