<?php

namespace App\Modules\Core\Http\Requests;

use App\Modules\Core\Support\Localization\Locales;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(Locales $locales): array
    {
        return [
            'locale' => ['required', 'string', Rule::in($locales->codes())],
        ];
    }
}
