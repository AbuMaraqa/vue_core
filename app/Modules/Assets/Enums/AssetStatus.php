<?php

namespace App\Modules\Assets\Enums;

use App\Modules\Foundation\Concerns\EnumOptions;

/**
 * Where an asset is in its life.
 */
enum AssetStatus: string
{
    use EnumOptions;

    case Active = 'active';
    case FullyDepreciated = 'fully_depreciated';
    case Disposed = 'disposed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('In use'),
            self::FullyDepreciated => __('Fully depreciated'),
            self::Disposed => __('Disposed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::FullyDepreciated => 'info',
            self::Disposed => 'light',
            self::Cancelled => 'error',
        };
    }

    /**
     * Whether the company still owns and depreciates it.
     */
    public function isOwned(): bool
    {
        return $this === self::Active || $this === self::FullyDepreciated;
    }
}
