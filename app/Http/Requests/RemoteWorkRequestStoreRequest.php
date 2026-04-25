<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoteWorkRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'region' => ['required', 'in:domestic,overseas'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:5000'],
            'deliverables' => ['required', 'string', 'max:5000'],
            'work_environment' => ['required', 'string', 'max:5000'],
        ];
    }
}
