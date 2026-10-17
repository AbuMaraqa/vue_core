<?php

namespace App\Modules\Foundation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The spreadsheet of an import. Authorization is done by the controller,
 * because each list has its own import permission.
 */
class ImportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:'.config()->integer('foundation.imports.max_file_size')],
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
            'file' => __('File'),
        ];
    }

    public function spreadsheet(): UploadedFile
    {
        $file = $this->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
