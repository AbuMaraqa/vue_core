<?php

namespace App\Modules\Core\Http\Requests\Users\Concerns;

use App\Modules\Core\Enums\UserRole;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Spatie\Permission\Models\Role;

trait ValidatesAssignableRoles
{
    /**
     * Only super admins may grant the super admin role.
     *
     * @return array<string, list<ConditionalRules|Exists|string>>
     */
    protected function roleRules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => [
                'string',
                'distinct',
                Rule::exists(Role::class, 'name'),
                Rule::when(! $this->user()->isSuperAdmin(), [Rule::notIn([UserRole::SuperAdmin->value])]),
            ],
        ];
    }
}
