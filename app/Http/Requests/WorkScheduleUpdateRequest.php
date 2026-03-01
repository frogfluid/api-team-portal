<?php

namespace App\Http\Requests;

use App\Models\WorkSchedule;
use Illuminate\Foundation\Http\FormRequest;

class WorkScheduleUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var WorkSchedule|null $schedule */
        $schedule = $this->route('workSchedule');

        return $schedule && (bool) $this->user()?->can('update', $schedule);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
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
