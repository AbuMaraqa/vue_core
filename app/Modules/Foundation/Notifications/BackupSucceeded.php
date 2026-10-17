<?php

namespace App\Modules\Foundation\Notifications;

use App\Modules\Foundation\Enums\NotificationLevel;

class BackupSucceeded extends SystemNotification
{
    public function __construct(public string $disk) {}

    protected function title(): string
    {
        return 'Backup completed';
    }

    protected function body(): string
    {
        return 'A new backup was saved to the :disk disk.';
    }

    protected function parameters(): array
    {
        return ['disk' => $this->disk];
    }

    protected function route(): ?array
    {
        return ['admin.backups.index'];
    }

    protected function level(): NotificationLevel
    {
        return NotificationLevel::Success;
    }
}
