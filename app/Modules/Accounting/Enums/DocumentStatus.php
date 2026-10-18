<?php

namespace App\Modules\Accounting\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * The life of a financial document (invoice, payment, adjustment, transfer).
 * Drafts have no effect; posted documents are in the books and stock;
 * cancelled documents stay visible with their effect reversed.
 */
enum DocumentStatus: string
{
    use EnumOptions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Posted => __('Posted'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'light',
            self::Posted => 'success',
            self::Cancelled => 'error',
        };
    }
}
