<?php

namespace App\Modules\Core\Http\Requests\Roles;

use App\Modules\Core\Http\Requests\Roles\Concerns\ValidatesGrantablePermissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StoreRoleRequest extends FormRequest
{
    use ValidatesGrantablePermissions;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(Role::class, 'name')],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists(Permission::class, 'name'), $this->grantablePermission(...)],
        ];
    }
}
