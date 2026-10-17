<?php

namespace App\Modules\Foundation\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IssueTokenRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * `code` (an authenticator code) or `recovery_code` is required for
     * accounts with two-factor authentication.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ];
    }
}
