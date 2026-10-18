<?php

namespace App\Modules\Accounting\Enums;

enum FiscalYearStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return $this === self::Open ? __('Open') : __('Closed');
    }
}
