<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('manage-admin-attendance');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'date' => ['sometimes', 'required', 'date'],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'status' => ['sometimes', 'required', 'in:normal,late,early_leave,absent,on_leave,rest_day,dedication'],
            'note' => ['nullable', 'string', 'max:5000'],
            'post_payroll' => ['sometimes', 'boolean'],
        ];
    }
}
