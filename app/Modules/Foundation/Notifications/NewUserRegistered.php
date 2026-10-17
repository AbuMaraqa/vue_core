<?php

namespace App\Modules\Foundation\Notifications;

use App\Models\User;
use App\Modules\Foundation\Enums\NotificationLevel;

/**
 * Tell the staff who manage users that someone created an account.
 */
class NewUserRegistered extends SystemNotification
{
    public function __construct(public User $user) {}

    protected function title(): string
    {
        return 'New user registered';
    }

    protected function body(): string
    {
        return ':name (:email) created an account.';
    }

    protected function parameters(): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
        ];
    }

    protected function route(): ?array
    {
        return ['admin.users.edit', ['user' => $this->user->id]];
    }

    protected function level(): NotificationLevel
    {
        return NotificationLevel::Success;
    }
}
