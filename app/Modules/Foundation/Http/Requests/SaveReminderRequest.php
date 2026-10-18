<?php

namespace App\Modules\Foundation\Http\Requests;

use App\Modules\Foundation\Models\Reminder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add or change a reminder of the calendar. Only its author may change it.
 */
class SaveReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reminder = $this->route('reminder');

        return $reminder instanceof Reminder
            ? $this->user()->can('update', $reminder)
            : $this->user()->can('create', Reminder::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', Rule::in(Reminder::COLORS)],
            'is_shared' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Title'),
            'date' => __('Date'),
            'time' => __('Time'),
            'notes' => __('Notes'),
            'color' => __('Color'),
        ];
    }

    /**
     * @return array{title: string, date: string, time: string|null, notes: string|null, color: string, is_shared: bool}
     */
    public function attributesToSave(): array
    {
        return [
            'title' => $this->string('title')->value(),
            'date' => $this->string('date')->value(),
            'time' => $this->filled('time') ? $this->string('time')->value() : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->value() : null,
            'color' => $this->string('color')->value(),
            'is_shared' => $this->boolean('is_shared'),
        ];
    }
}
