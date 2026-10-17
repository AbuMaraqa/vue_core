<?php

namespace App\Modules\Core\Console\Commands;

use App\Modules\Core\Actions\Permissions\SyncPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync {--prune : Delete permissions that are no longer registered by any module}')]
#[Description('Create the permissions registered by the modules and optionally prune stale ones')]
class SyncPermissionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncPermissions $syncPermissions): int
    {
        ['created' => $created, 'deleted' => $deleted] = $syncPermissions->handle(prune: (bool) $this->option('prune'));

        $this->components->info(sprintf('%d permission(s) created, %d deleted.', count($created), count($deleted)));

        foreach ($created as $name) {
            $this->components->twoColumnDetail($name, '<fg=green>CREATED</>');
        }

        foreach ($deleted as $name) {
            $this->components->twoColumnDetail($name, '<fg=red>DELETED</>');
        }

        return self::SUCCESS;
    }
}
