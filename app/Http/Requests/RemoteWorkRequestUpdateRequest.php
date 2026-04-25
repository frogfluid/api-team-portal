<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoteWorkRequestUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'region' => ['sometimes', 'in:domestic,overseas'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'reason' => ['sometimes', 'string', 'max:5000'],
            'deliverables' => ['sometimes', 'string', 'max:5000'],
            'work_environment' => ['sometimes', 'string', 'max:5000'],
        ];
    }
}
