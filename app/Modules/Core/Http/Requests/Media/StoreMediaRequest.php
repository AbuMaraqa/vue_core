<?php

namespace App\Modules\Core\Http\Requests\Media;

use App\Modules\Core\Enums\Permissions\MediaPermission;
use App\Modules\Core\Models\Site;
use App\Modules\Core\Rules\ValidTemporaryUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(MediaPermission::Create);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uploads' => ['required', 'array', 'min:1', 'max:20'],
            'uploads.*' => ['string', new ValidTemporaryUpload(Site::IMAGE_MIME_TYPES, 10240)],
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
            'uploads' => __('Files'),
        ];
    }
}
