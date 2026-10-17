<?php

namespace App\Modules\Core\Actions\Users;

use App\Models\User;
use App\Modules\Core\Data\UserData;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    /**
     * Create a user from the admin panel. Accounts created by staff are trusted,
     * so their email address is marked as verified.
     */
    public function handle(UserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $user->syncRoles($data->roles);

            return $user;
        });
    }
}
