<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiEvaluationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update', $this->route('evaluation'));
    }

    public function rules(): array
    {
        $evaluation = $this->route('evaluation');
        $evaluationId = $evaluation?->id;
        $userId = $this->input('user_id', $evaluation?->user_id);

        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'year_month' => [
                'sometimes',
                'string',
                'regex:/^\d{4}-\d{2}$/',
                Rule::unique('ai_evaluations', 'year_month')
                    ->ignore($evaluationId)
                    ->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'model' => ['sometimes', 'nullable', 'string', 'max:64'],
            'evaluator_note' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'status' => ['sometimes', 'nullable', 'in:draft,pending,published,archived'],
            'content' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:5'],
        ];
    }
}
