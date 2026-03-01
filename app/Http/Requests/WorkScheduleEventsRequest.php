<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkScheduleEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ログイン済みならOK（より厳密にするなら後述）
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'updated_after' => ['nullable', 'date'],
            'timezone' => ['nullable', 'timezone'],

            // チーム表示用（未指定ならコントローラ側で自分にフォールバック）
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
