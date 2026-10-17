<?php

namespace App\Modules\Core\Actions\Users;

use App\Models\User;
use App\Modules\Core\Data\UserData;
use Illuminate\Support\Facades\DB;

class UpdateUser
{
    /**
     * Update a user from the admin panel. The password is only changed when provided.
     */
    public function handle(User $user, UserData $data): User
    {
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
