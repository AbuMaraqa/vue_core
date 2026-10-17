<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use UnitEnum;

use function Illuminate\Support\enum_value;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Give the user the super admin role, which bypasses permission checks.
     */
    public function superAdmin(): static
    {
        return $this->withRole(UserRole::SuperAdmin);
    }

    /**
     * Give the user the given role, creating it when needed.
     */
    public function withRole(UnitEnum|string $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(
            Role::findOrCreate(enum_value($role), 'web'),
        ));
    }

    /**
     * Give the user the given permissions directly, creating them when needed.
     */
    public function withPermissions(UnitEnum|string ...$permissions): static
    {
        return $this->afterCreating(fn (User $user) => $user->givePermissionTo(
            array_map(fn (UnitEnum|string $permission): PermissionContract => Permission::findOrCreate(enum_value($permission), 'web'), $permissions),
        ));
    }
}
