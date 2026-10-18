<?php

namespace App\Modules\Accounting\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Drafts can be edited freely; posted entries are part of the books and can
 * only be undone by a reversing entry.
 */
enum JournalEntryStatus: string
{
    use EnumOptions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Posted => __('Posted'),
            self::Reversed => __('Reversed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'light',
            self::Posted => 'success',
            self::Reversed => 'warning',
        };
    }
}
