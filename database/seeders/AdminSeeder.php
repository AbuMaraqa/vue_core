<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Create the default super admin account.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Super Admin', 'password' => 'password'],
        );

        $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now()])->save();

        $admin->syncRoles(UserRole::SuperAdmin);
    }
}
