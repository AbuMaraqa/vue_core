<?php

namespace App\Modules\Accounting\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Which documents a tax can be chosen on.
 */
enum TaxScope: string
{
    use EnumOptions;

    case Sales = 'sales';
    case Purchases = 'purchases';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Sales => __('Sales'),
            self::Purchases => __('Purchases'),
            self::Both => __('Sales and purchases'),
        };
    }

    public function appliesToSales(): bool
    {
        return $this !== self::Purchases;
    }

    public function appliesToPurchases(): bool
    {
        return $this !== self::Sales;
    }
}
