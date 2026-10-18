<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\FiscalYearStatus;
use Carbon\CarbonInterface;
use Database\Factories\FiscalYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property FiscalYearStatus $status
 * @property int|null $closing_entry_id
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JournalEntry|null $closingEntry
 */
#[Fillable(['name', 'start_date', 'end_date', 'status', 'closing_entry_id', 'closed_at', 'closed_by'])]
#[UseFactory(FiscalYearFactory::class)]
class FiscalYear extends Model
{
    /** @use HasFactory<FiscalYearFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function closingEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'closing_entry_id');
    }

    /**
     * The fiscal year that contains the date.
     *
     * @param  Builder<FiscalYear>  $query
     */
    public function scopeContaining(Builder $query, CarbonInterface|string $date): void
    {
        $day = Carbon::parse($date)->toDateString();

        $query->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day);
    }

    public function isOpen(): bool
    {
        return $this->status === FiscalYearStatus::Open;
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => FiscalYearStatus::class,
            'closed_at' => 'datetime',
        ];
    }
}
