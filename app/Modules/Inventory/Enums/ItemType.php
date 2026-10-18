<?php

namespace App\Modules\Inventory\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Stock items are counted and valued in warehouses; services are only sold
 * or bought, with no stock.
 */
enum ItemType: string
{
    use EnumOptions;

    case Stock = 'stock';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Stock => __('Stock item'),
            self::Service => __('Service'),
        };
    }
}
