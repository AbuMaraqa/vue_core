<?php

namespace App\Modules\Foundation\Http\Requests;

use App\Modules\Foundation\Printing\PaperSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrintSettingsRequest extends FormRequest
{
    /**
     * The route already requires `Setting Update`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'paper_size' => ['required', Rule::enum(PaperSize::class)],
            'show_logo' => ['required', 'boolean'],
            'show_printed_by' => ['required', 'boolean'],
            'footer_note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'paper_size' => __('Paper size'),
            'footer_note' => __('Footer note'),
        ];
    }
}
