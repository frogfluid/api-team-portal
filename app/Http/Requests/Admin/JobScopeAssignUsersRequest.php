<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class JobScopeAssignUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scope = $this->route('jobScope');

        return $this->user() && $scope && $this->user()->can('update', $scope);
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
