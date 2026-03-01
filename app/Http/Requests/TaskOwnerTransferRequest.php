<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskOwnerTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // 後で管理者限定にするならここ
    }

    public function rules(): array
    {
        return [
            'to_owner_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
