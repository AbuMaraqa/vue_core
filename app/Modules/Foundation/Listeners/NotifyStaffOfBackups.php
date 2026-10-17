<?php

namespace App\Modules\Foundation\Listeners;

use App\Modules\Foundation\Notifications\BackupFailed;
use App\Modules\Foundation\Notifications\BackupSucceeded;
use App\Modules\Foundation\Support\Recipients;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;

/**
 * Tell the staff who manage backups how each backup ended.
 */
class NotifyStaffOfBackups
{
    public function handleSuccess(BackupWasSuccessful $event): void
    {
        Notification::send(Recipients::withPermission('Backup View List'), new BackupSucceeded($event->diskName));
    }

    public function handleFailure(BackupHasFailed $event): void
    {
        report($event->exception);

        Notification::send(Recipients::withPermission('Backup View List'), new BackupFailed);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            BackupWasSuccessful::class => 'handleSuccess',
            BackupHasFailed::class => 'handleFailure',
        ];
    }
}
