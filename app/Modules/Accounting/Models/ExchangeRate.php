<?php

namespace App\Modules\Accounting\Models;

use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How much one unit of the currency is worth in the base currency, from a date
 * until the next rate.
 *
 * @property int $id
 * @property int $currency_id
 * @property Carbon $effective_date
 * @property string $rate
 * @property-read Currency $currency
 */
#[Fillable(['currency_id', 'effective_date', 'rate'])]
#[UseFactory(ExchangeRateFactory::class)]
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'rate' => 'decimal:8',
        ];
    }
}
