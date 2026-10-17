<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use App\Modules\Foundation\Enums\AuthenticationEvent;
use Database\Factories\AuthenticationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entry of the login history, written by RecordAuthenticationEvents.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property AuthenticationEvent $event
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'email', 'event', 'ip_address', 'user_agent'])]
#[UseFactory(AuthenticationLogFactory::class)]
class AuthenticationLog extends Model
{
    /** @use HasFactory<AuthenticationLogFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Entries are kept for `foundation.login_history.retention_days`.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(config()->integer('foundation.login_history.retention_days')));
    }

    protected function casts(): array
    {
        return [
            'event' => AuthenticationEvent::class,
            'created_at' => 'datetime',
        ];
    }
}
