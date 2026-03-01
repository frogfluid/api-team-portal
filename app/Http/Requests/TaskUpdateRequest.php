<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:opened,in_progress,on_hold,blocked,done'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'due_at' => ['nullable', 'date'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
