<?php

namespace App\Modules\Foundation\Exports;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * @extends Exporter<User>
 */
class UsersExporter extends Exporter
{
    public function title(): string
    {
        return 'Users';
    }

    public function columns(): array
    {
        return [
            'Name' => fn (User $user): string => $user->name,
            'Email' => fn (User $user): string => $user->email,
            'Roles' => fn (User $user): string => $user->roles->map(fn (Role $role): string => __($role->name))->implode(', '),
            'Verified' => fn (User $user): bool => $user->email_verified_at !== null,
            'Joined' => fn (User $user): ?string => $user->created_at?->format('Y-m-d H:i'),
        ];
    }
}
