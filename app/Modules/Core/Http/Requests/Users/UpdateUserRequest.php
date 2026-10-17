<?php

namespace App\Modules\Core\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Modules\Core\Http\Requests\Users\Concerns\ValidatesAssignableRoles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules, ValidatesAssignableRoles;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetUser());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The password is optional; it is only changed when a new one is provided.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->targetUser()->id),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            ...$this->roleRules(),
        ];
    }

    protected function targetUser(): User
    {
        $user = $this->route('user');

        abort_unless($user instanceof User, 404);

        return $user;
    }
}
