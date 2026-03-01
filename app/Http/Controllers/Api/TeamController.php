<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveQuota;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentYear = now()->year;
        $quotas = LeaveQuota::where('year', $currentYear)->get()->keyBy('user_id');

        $users = User::query()
            ->where('is_active', true)
            ->with('employee:id,user_id,phone')
            ->select('id', 'name', 'email', 'role', 'department', 'avatar_path', 'is_active')
            ->orderBy('name')
            ->get()
            ->map(function ($user) use ($quotas) {
                $quota = $quotas->get($user->id);
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'avatar_url' => $user->avatar_url,
                    'phone' => $user->employee?->phone,
                    'is_active' => (bool) $user->is_active,
                    'department' => $user->department?->value,
                    'annual_quota' => $quota ? (float) $quota->annual_total : null,
                    'sick_quota' => $quota ? (float) $quota->sick_total : null,
                ];
            });

        return response()->json($users);
    }
}
