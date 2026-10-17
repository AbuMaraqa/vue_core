<?php

namespace App\Modules\Core\Http\Requests\Roles;

use App\Modules\Core\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->role());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Roles the system relies on keep their name, only their permissions change.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->role();

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Role::class, 'name')->ignore($role),
                Rule::when(UserRole::isProtected($role->name), [Rule::in([$role->name])]),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists(Permission::class, 'name')],
        ];
    }

    protected function role(): Role
    {
        $role = $this->route('role');

        abort_unless($role instanceof Role, 404);

        return $role;
    }
}
