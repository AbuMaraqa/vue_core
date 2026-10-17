<?php

namespace App\Modules\Foundation\Listeners;

use App\Models\User;
use App\Modules\Foundation\Notifications\NewUserRegistered;
use App\Modules\Foundation\Support\Recipients;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Notification;

class NotifyStaffOfNewUser
{
    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $recipients = Recipients::withPermission('User View List')
            ->reject(fn (User $recipient): bool => $recipient->is($event->user));

        Notification::send($recipients, new NewUserRegistered($event->user));
    }
}
