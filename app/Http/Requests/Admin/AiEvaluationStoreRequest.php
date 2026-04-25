<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiEvaluationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', \App\Models\AiEvaluation::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year_month' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{2}$/',
                Rule::unique('ai_evaluations', 'year_month')
                    ->where(fn ($q) => $q->where('user_id', $this->input('user_id'))),
            ],
            'model' => ['nullable', 'string', 'max:64'],
            'evaluator_note' => ['nullable', 'string', 'max:65535'],
            'status' => ['nullable', 'in:draft,pending,published,archived'],
            'content' => ['nullable', 'string', 'max:65535'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:5'],
        ];
    }
}
