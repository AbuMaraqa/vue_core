<?php

namespace App\Modules\Core\Actions\Users;

use App\Models\User;
use App\Modules\Core\Data\UserData;
use App\Modules\Core\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    /**
     * Update a user from the admin panel. The password is only changed when provided.
     */
    public function handle(User $user, UserData $data): User
    {
        if ($user->isLastSuperAdmin() && ! in_array(UserRole::SuperAdmin->value, $data->roles, true)) {
            throw ValidationException::withMessages([
                'roles' => __('This is the only :role. Give the role to someone else first.', ['role' => __(UserRole::SuperAdmin->value)]),
            ]);
        }

        return DB::transaction(function () use ($user, $data): User {
            $user->fill([
                'name' => $data->name,
                'email' => $data->email,
            ]);

            if (filled($data->password)) {
                $user->password = $data->password;
            }

            $user->save();

            $user->syncRoles($data->roles);

            return $user;
        });
    }
}
