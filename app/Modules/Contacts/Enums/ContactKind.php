<?php

namespace App\Modules\Contacts\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Whether a contact is a person or an organisation.
 */
enum ContactKind: string
{
    use EnumOptions;

    case Company = 'company';
    case Individual = 'individual';

    public function label(): string
    {
        return match ($this) {
            self::Company => __('Company'),
            self::Individual => __('Individual'),
        };
    }
}
