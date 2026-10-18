<?php

namespace App\Modules\Production\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * The life of a production order. A draft is only a plan; starting it moves
 * the materials out of their warehouse into work in progress; completing it
 * adds the other costs and brings the product into stock at the full cost.
 * Each step can be taken back, and a draft that was started once is
 * cancelled instead of deleted, so its history stays visible.
 */
enum ProductionOrderStatus: string
{
    use EnumOptions;

    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::InProgress => __('In progress'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'light',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'error',
        };
    }
}
