<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Support\Currencies;
use App\Modules\Foundation\Concerns\HasTranslations;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property string|null $symbol
 * @property int $decimal_places
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'symbol', 'decimal_places', 'is_active'])]
#[UseFactory(CurrencyFactory::class)]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory, HasTranslations;

    /**
     * Make the request's {@see Currencies} read the base currency again after
     * any currency changes.
     */
    protected static function booted(): void
    {
        $forgetBase = function (): void {
            if (app()->resolved(Currencies::class)) {
                app(Currencies::class)->forgetBase();
            }
        };

        static::saved($forgetBase);
        static::deleted($forgetBase);
    }

    /**
     * @return HasMany<ExchangeRate, $this>
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
