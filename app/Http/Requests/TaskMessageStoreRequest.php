<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskMessageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:comment,progress_update,question,decision'],
            'body' => ['nullable', 'string', 'max:10000'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:10240'], // 10MB each
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if ($this->input('type') === 'progress_update' && $this->input('progress') === null) {
                $v->errors()->add('progress', 'Progress is required for progress updates.');
            }
            if ($this->input('type') !== 'progress_update' && $this->input('progress') !== null) {
                // ignore or error; here we ignore by not erroring
            }
        });
    }
}
