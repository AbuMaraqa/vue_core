<?php

namespace App\Modules\Foundation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What to back up. The permission is checked by the route.
 */
class CreateBackupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:database,full'],
        ];
    }

    public function databaseOnly(): bool
    {
        return $this->validated('type') === 'database';
    }
}
