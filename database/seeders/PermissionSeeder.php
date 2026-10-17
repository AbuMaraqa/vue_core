<?php

namespace Database\Seeders;

use App\Modules\Core\Actions\Permissions\SyncPermissions;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Create every permission registered by the modules.
     */
    public function run(SyncPermissions $syncPermissions): void
    {
        $syncPermissions->handle();
    }
}
