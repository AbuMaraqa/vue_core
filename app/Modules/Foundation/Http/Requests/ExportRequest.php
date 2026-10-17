<?php

namespace App\Modules\Foundation\Http\Requests;

use App\Modules\Foundation\Exports\ExportFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The format of an export. Authorization is done by the controller, because
 * each list has its own export permission.
 */
class ExportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::enum(ExportFormat::class)],
        ];
    }

    public function exportFormat(): ExportFormat
    {
        return $this->enum('format', ExportFormat::class) ?? ExportFormat::Xlsx;
    }
}
