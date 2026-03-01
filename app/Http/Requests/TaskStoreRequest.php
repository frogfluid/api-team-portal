<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // 後で管理者制限に変えるならここ
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:opened,in_progress,on_hold,blocked,done'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'due_at' => ['nullable', 'date'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
