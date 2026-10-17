<?php

namespace App\Modules\Foundation\Notifications;

use App\Modules\Foundation\Enums\NotificationLevel;

/**
 * The error itself is logged, not shown: it may contain server details.
 */
class BackupFailed extends SystemNotification
{
    protected function title(): string
    {
        return 'Backup failed';
    }

    protected function body(): string
    {
        return 'The backup could not be completed. Check the logs for the reason.';
    }

    protected function route(): ?array
    {
        return ['admin.backups.index'];
    }

    protected function level(): NotificationLevel
    {
        return NotificationLevel::Error;
    }
}
