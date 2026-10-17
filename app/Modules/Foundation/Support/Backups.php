<?php

namespace App\Modules\Foundation\Support;

use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * The backups spatie/laravel-backup wrote to the disks configured in
 * `backup.backup.destination.disks`.
 *
 * Backups are identified by a hash of their disk and path, so file paths are
 * never taken from the request.
 */
class Backups
{
    /**
     * @return list<array{id: string, disk: string, name: string, size: int, created_at: string}>
     */
    public function all(): array
    {
        $backups = [];

        foreach ($this->destinations() as $destination) {
            foreach ($destination->backups() as $backup) {
                $backups[] = [
                    'id' => $this->id($destination->diskName(), $backup->path()),
                    'disk' => $destination->diskName(),
                    'name' => basename($backup->path()),
                    'size' => (int) $backup->sizeInBytes(),
                    'created_at' => $backup->date()->toIso8601String(),
                ];
            }
        }

        usort($backups, fn (array $first, array $second): int => strcmp($second['created_at'], $first['created_at']));

        return $backups;
    }

    public function find(string $id): ?Backup
    {
        foreach ($this->destinations() as $destination) {
            foreach ($destination->backups() as $backup) {
                if (hash_equals($this->id($destination->diskName(), $backup->path()), $id)) {
                    return $backup;
                }
            }
        }

        return null;
    }

    /**
     * @return list<BackupDestination>
     */
    protected function destinations(): array
    {
        /** @var list<string> $disks */
        $disks = config()->array('backup.backup.destination.disks');

        return array_map(
            fn (string $disk): BackupDestination => BackupDestination::create($disk, config()->string('backup.backup.name')),
            $disks,
        );
    }

    protected function id(string $disk, string $path): string
    {
        return hash_hmac('sha256', "{$disk}|{$path}", config()->string('app.key'));
    }
}
