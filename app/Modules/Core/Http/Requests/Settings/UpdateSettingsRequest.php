<?php

namespace App\Modules\Core\Http\Requests\Settings;

use App\Modules\Core\Enums\FontFamily;
use App\Modules\Core\Http\Requests\Concerns\ValidatesMediaInput;
use App\Modules\Core\Models\Site;
use App\Modules\Core\Support\Localization\Locales;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    use ValidatesMediaInput;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('Setting Update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(Locales $locales): array
    {
        return [
            'general.site_name' => ['required', 'string', 'max:100'],
            'general.site_description' => ['nullable', 'string', 'max:500'],
            'general.contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'general.contact_phone' => ['nullable', 'string', 'max:30'],
            'localization.default_locale' => ['required', 'string', Rule::in($locales->codes())],
            'appearance.font_family' => ['required', Rule::enum(FontFamily::class)],
            'appearance.primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ...$this->mediaRules('logo', Site::IMAGE_MIME_TYPES, maxKilobytes: 2048, maxFiles: 1),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'general.site_name' => __('Site name'),
            'general.site_description' => __('Site description'),
            'general.contact_email' => __('Contact email'),
            'general.contact_phone' => __('Contact phone'),
            'localization.default_locale' => __('Default language'),
            'appearance.font_family' => __('Font'),
            'appearance.primary_color' => __('Primary color'),
        ];
    }
}
