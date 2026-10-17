<?php

namespace App\Modules\Core\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTemporaryUploadRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The accepted types of each field are checked again when the form that
     * uses the upload is saved.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKilobytes = intdiv(config()->integer('media-library.max_file_size'), 1024);

        return [
            'file' => ['required', 'file', "max:{$maxKilobytes}"],
        ];
    }
}
