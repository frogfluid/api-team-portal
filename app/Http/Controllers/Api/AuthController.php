<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login and issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        // Revoke existing tokens for this device
        $user->tokens()->where('name', 'ios-app')->delete();

        $token = $user->createToken('ios-app')->plainTextToken;

        $user->load('employee');

        return response()->json([
            'token' => $token,
            'user' => $this->transformUser($user),
        ]);
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Return the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('employee');

        return response()->json($this->transformUser($user));
    }

    /**
     * Refresh token (revoke old, issue new).
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken('ios-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->transformUser($user->load('employee')),
        ]);
    }

    /**
     * Store device token for push notifications.
     */
    public function storeDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => ['required', 'string'],
            'platform' => ['nullable', 'string', 'in:ios,android'],
        ]);

        $user = $request->user();
        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $prefs['device_token'] = $request->input('device_token');
        $prefs['device_platform'] = $request->input('platform', 'ios');
        $user->update(['preferences' => $prefs]);

        return response()->json(['message' => 'Device token saved.']);
    }

    /**
     * Transform user to flat JSON matching Swift UserData struct.
     * All employee fields are flattened to the top level.
     * Key 'avatar' (not 'avatar_url') to match Swift property name.
     */
    private function transformUser(User $user): array
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
