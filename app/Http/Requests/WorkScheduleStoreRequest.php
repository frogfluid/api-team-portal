<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkScheduleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'in:work,leave'],
            'leave_type' => ['nullable', 'string', 'in:annual,sick'],
            'all_day' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'timezone'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'repeat_weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
            'save_template' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
            'manager_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
