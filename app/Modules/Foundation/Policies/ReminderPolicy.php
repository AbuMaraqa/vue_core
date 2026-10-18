<?php

namespace App\Modules\Foundation\Policies;

use App\Models\User;
use App\Modules\Foundation\Models\Reminder;

/**
 * Everyone who can open the calendar keeps their own reminders; a shared
 * reminder is seen by all of them but changed only by its author.
 */
class ReminderPolicy
{
    public function create(User $user): bool
    {
        return $user->can('Calendar View');
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $reminder->user_id === $user->id && $user->can('Calendar View');
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->update($user, $reminder);
    }
}
