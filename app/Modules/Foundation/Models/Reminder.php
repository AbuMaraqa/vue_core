<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A note a staff member puts on the calendar, e.g. "deliver the kitchen to
 * Ahmad" or "renew the trade licence". It is private to its author unless
 * shared with everyone who can open the calendar; only the author changes
 * it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property Carbon $date
 * @property string|null $time
 * @property string|null $notes
 * @property string $color
 * @property bool $is_shared
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'title', 'date', 'time', 'notes', 'color', 'is_shared'])]
#[UseFactory(ReminderFactory::class)]
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    public const array COLORS = ['brand', 'info', 'success', 'warning', 'error', 'orange', 'purple', 'pink', 'gray'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user's own reminders and those shared with everyone.
     *
     * @param  Builder<Reminder>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $visible) => $visible->where('user_id', $user->id)->orWhere('is_shared', true));
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_shared' => 'boolean',
        ];
    }
}
