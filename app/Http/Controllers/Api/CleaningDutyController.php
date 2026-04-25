<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningDuty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CleaningDutyController extends Controller
{
    /**
     * GET /api/cleaning-duty/today
     * Visible to everyone — they need to know if it's their turn.
     * Returns null if no duty assigned for today.
     */
    public function today(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $duty = CleaningDuty::where('date', $today)->first();

        if ($duty === null) {
            return response()->json(['duty' => null]);
        }

        return response()->json(['duty' => $this->transform($duty)]);
    }

    /**
     * GET /api/cleaning-duty?from=YYYY-MM-DD&to=YYYY-MM-DD
     * Admin-only — history of assignments.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $q = CleaningDuty::query()->orderByDesc('date');
        if ($request->filled('from')) {
            $q->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('date', '<=', $request->to);
        }
        $duties = $q->limit(60)->get();

        return response()->json([
            'duties' => $duties->map(fn ($d) => $this->transform($d)),
        ]);
    }

    /**
     * POST /api/cleaning-duty
     * Admin-only — assign today's cleaning duty.
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $validated['date'] ?? Carbon::today()->toDateString();

        $duty = CleaningDuty::updateOrCreate(
            ['date' => $date],
            [
                'assigned_user_ids' => array_values(array_map('intval', $validated['user_ids'])),
                'assigned_by' => $request->user()->id,
            ]
        );

        return response()->json(['duty' => $this->transform($duty)], 201);
    }

    // MARK: - Helpers

    private function ensureAdmin(Request $request): void
    {
        if (! ($request->user()->role instanceof UserRole) || $request->user()->role !== UserRole::ADMIN) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Admin only.');
        }
    }

    private function transform(CleaningDuty $duty): array
    {
        $userIds = $duty->assigned_user_ids ?? [];
        $users = User::whereIn('id', $userIds)
            ->select(['id', 'name', 'avatar_path'])
            ->get();

        return [
            'id' => $duty->id,
            'date' => optional($duty->date)->toDateString(),
            'assigned_user_ids' => $userIds,
            'assigned_by' => $duty->assigned_by,
            'assigned_users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_path' => $u->avatar_path,
            ])->values(),
            'created_at' => optional($duty->created_at)->toIso8601String(),
        ];
    }
}
