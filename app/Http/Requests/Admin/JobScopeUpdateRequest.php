<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class JobScopeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scope = $this->route('jobScope');

        return $this->user() && $scope && $this->user()->can('update', $scope);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'has_external_output' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
