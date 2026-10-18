<?php

namespace App\Modules\Core\Http\Requests\Users\Concerns;

use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Spatie\Permission\Models\Role;

trait ValidatesAssignableRoles
{
    /**
     * A role can only be given by someone holding all of its permissions;
     * only super admins grant the super admin role.
     *
     * @return array<string, list<Closure|Exists|string>>
     */
    protected function roleRules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => [
                'string',
                'distinct',
                Rule::exists(Role::class, 'name'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $role = is_string($value) ? Role::query()->with('permissions')->where('name', $value)->first() : null;

                    if ($role !== null && ! $this->user()->canGrantRole($role)) {
                        $fail(__('You cannot grant the :role role: it has permissions you do not hold.', ['role' => __($role->name)]));
                    }
                },
            ],
        ];
    }
}
