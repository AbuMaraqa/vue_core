<?php

namespace App\Models;

use App\Modules\Core\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $locale
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the user bypasses permission checks.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SuperAdmin);
    }

    /**
     * The names of the permissions the user holds, for interfaces that show or
     * hide actions. Super admins hold every permission, read from the
     * permission cache rather than the database.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $permissions = $this->isSuperAdmin()
            ? app(PermissionRegistrar::class)->getPermissions()
            : $this->getAllPermissions();

        return array_values($permissions->map(fn (Permission $permission): string => $permission->name)->all());
    }

    /**
     * The only super admin left: removing their role or account would leave
     * nobody able to manage roles, permissions or other super admins.
     */
    public function isLastSuperAdmin(): bool
    {
        return $this->isSuperAdmin()
            && User::role(UserRole::SuperAdmin->value)->whereKeyNot($this->getKey())->doesntExist();
    }

    /**
     * Whether the user may hand these permissions to someone else. Nobody
     * grants a permission they do not hold themselves, so holding `User
     * Update` or `Roles And Permissions Update` never lets a user raise
     * their own (or anyone's) access above their own.
     *
     * @param  iterable<string>  $permissions
     */
    public function canGrantPermissions(iterable $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return collect($permissions)->diff($this->permissionNames())->isEmpty();
    }

    /**
     * Whether the user may give someone this role: only super admins grant
     * the super admin role, and nobody grants a role with more access than
     * their own.
     */
    public function canGrantRole(Role $role): bool
    {
        if ($role->name === UserRole::SuperAdmin->value) {
            return $this->isSuperAdmin();
        }

        return $this->canGrantPermissions($role->permissions->pluck('name'));
    }

    /**
     * Whether the user may manage another account (edit it, reset its
     * password, delete it): never one with access they do not have.
     */
    public function canManage(User $other): bool
    {
        if ($other->isSuperAdmin()) {
            return $this->isSuperAdmin();
        }

        return $this->canGrantPermissions($other->permissionNames());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
