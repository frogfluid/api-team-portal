<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('employee');

        return response()->json($this->transformUser($user));
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email'],
            'date_of_birth' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $user->fill($request->only(['name', 'email']));
        $user->save();

        $employee = $user->employee()->firstOrCreate([]);
        $employee->fill($request->only(['date_of_birth', 'phone', 'address', 'legal_name', 'nationality']));
        $employee->save();

        return response()->json($this->transformUser($user->fresh()->load('employee')));
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars/' . $user->id, 'public');
        $user->forceFill(['avatar_path' => $path])->save();

        return response()->json([
            'message' => 'Avatar updated.',
            'avatar_url' => $user->avatar_url,
        ]);
    }

    private function transformUser($user): array
    {
        $employee = $user->employee;

        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'role'            => $user->role?->value,
            'avatar'          => $user->avatar_url,
            'phone'           => $employee?->phone,
            'department'      => $user->department?->value,
            'department_id'   => null,
            'device_token'    => $user->preferences['device_token'] ?? null,
            'last_active_at'  => $user->last_active_at?->toIso8601String(),
            'legal_name'      => $employee?->legal_name,
            'employee_no'     => $employee?->employee_no,
            'employment_type' => $employee?->employment_type,
            'date_of_birth'   => $employee?->date_of_birth?->toDateString(),
            'address'         => $employee?->address,
            'nationality'     => $employee?->nationality,
            'joined_on'       => $employee?->joined_on?->toDateString(),
            'left_on'         => $employee?->left_on?->toDateString(),
            'is_active'       => $user->is_active,
            'note'            => $employee?->note,
        ];
    }
}
