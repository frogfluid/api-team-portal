<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LeaveQuota;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized.');
        }

        $keyword = trim((string) $request->query('q', ''));
        $currentYear = now()->year;
        $quotas = LeaveQuota::where('year', $currentYear)->get()->keyBy('user_id');

        $users = User::query()
            ->with('employee:id,user_id,phone,employee_no,department_id')
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($nested) use ($keyword) {
                    $nested->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function ($user) use ($quotas) {
                return $this->transformUser($user, $quotas);
            });

        return response()->json($users->values());
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string'],
            'role' => ['sometimes', 'string', 'in:admin,manager,member,intern'],
            'department' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('role')) {
            $user->role = UserRole::from($request->role);
        }

        if ($request->has('department')) {
            $user->department = $request->department;
        }

        if ($request->has('is_active')) {
            if (!$request->is_active && $user->id === $request->user()->id) {
                return response()->json(['message' => 'Cannot deactivate yourself.'], 422);
            }
            $user->is_active = $request->boolean('is_active');
        }

        // Update phone on the employee record if present
        if ($request->has('phone') && $user->employee) {
            $user->employee->phone = $request->phone;
            $user->employee->save();
        }

        $user->save();
        $user->load('employee:id,user_id,phone,employee_no,department_id');

        $currentYear = now()->year;
        $quotas = LeaveQuota::where('year', $currentYear)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('user_id');

        return response()->json($this->transformUser($user, $quotas));
    }

    /**
     * Transform a User model into the flat format expected by iOS TeamMemberData.
     */
    private function transformUser(User $user, $quotas): array
    {
        $quota = $quotas->get($user->id);
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value ?? 'member',
            'avatar_url' => $user->avatar_url,
            'phone' => $user->employee?->phone,
            'is_active' => (bool) $user->is_active,
            'department' => $user->department?->value ?? null,
            'annual_quota' => $quota ? (float) $quota->annual_total : null,
            'sick_quota' => $quota ? (float) $quota->sick_total : null,
        ];
    }
}
