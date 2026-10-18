<?php

namespace App\Modules\Foundation\Imports;

use App\Models\User;
use App\Modules\Core\Actions\Users\CreateUser;
use App\Modules\Core\Data\UserData;
use App\Modules\Core\Enums\UserRole;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Create users in bulk from a spreadsheet with the columns
 * `name, email, password, roles`. Roles are comma separated; a missing
 * password is replaced by a random one, so the user resets it by email.
 */
class UsersImporter extends Importer
{
    /**
     * The emails of the rows validated so far.
     *
     * @var array<string, true>
     */
    protected array $seenEmails = [];

    /**
     * @param  User  $importer  The staff member running the import: only super
     *                          admins may grant the super admin role.
     */
    public function __construct(
        protected User $importer,
        protected CreateUser $createUser,
    ) {}

    public function columns(): array
    {
        return ['name', 'email', 'password', 'roles'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email'), $this->firstInFile(...)],
            'password' => ['nullable', 'string', Password::defaults()],
            'roles' => ['nullable', 'string', $this->validRoles(...)],
        ];
    }

    protected function importRow(array $row): void
    {
        $this->createUser->handle(new UserData(
            name: (string) $row['name'],
            email: (string) $row['email'],
            roles: $this->roleNames($row['roles'] ?? null),
            password: filled($row['password'] ?? null) ? (string) $row['password'] : Str::password(24),
        ));
    }

    /**
     * The database check cannot see the other rows of the file, and a second
     * row with the same email would fail the whole import at the unique index.
     */
    protected function firstInFile(string $attribute, mixed $value, Closure $fail): void
    {
        $email = Str::lower(trim((string) $value));

        if (isset($this->seenEmails[$email])) {
            $fail(__('This email appears more than once in the file.'));

            return;
        }

        $this->seenEmails[$email] = true;
    }

    /**
     * Every listed role must exist and be one the importer may grant.
     */
    protected function validRoles(string $attribute, mixed $value, Closure $fail): void
    {
        $names = $this->roleNames($value);
        $existing = Role::query()->whereIn('name', $names)->pluck('name')->all();

        foreach (array_diff($names, $existing) as $unknown) {
            $fail(__('The role :role does not exist.', ['role' => $unknown]));
        }

        if (in_array(UserRole::SuperAdmin->value, $names, true) && ! $this->importer->isSuperAdmin()) {
            $fail(__('Only super admins may grant the :role role.', ['role' => __(UserRole::SuperAdmin->value)]));

            return;
        }

        foreach (Role::query()->with('permissions')->whereIn('name', $existing)->get() as $role) {
            if (! $this->importer->canGrantRole($role)) {
                $fail(__('You cannot grant the :role role: it has permissions you do not hold.', ['role' => __($role->name)]));
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function roleNames(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }
}
