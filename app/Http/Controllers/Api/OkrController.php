<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KeyResult;
use App\Models\Objective;
use App\Models\OkrCheckIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OkrController extends Controller
{
    /**
     * List objectives for the current user (or all for admin).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Objective::with(['owner:id,name,avatar_path', 'keyResults'])
            ->roots();

        if (!$user->canAccessAdmin()) {
            $query->forOwner($user->id);
        }

        if ($request->filled('period')) {
            $query->forPeriod($request->period);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $objectives = $query->latest()->paginate(20);

        return response()->json([
            'data' => $objectives->map(fn($o) => $this->transformObjective($o)),
            'meta' => [
                'current_page' => $objectives->currentPage(),
                'last_page' => $objectives->lastPage(),
                'total' => $objectives->total(),
                'current_period' => Objective::currentPeriod(),
            ],
        ]);
    }

    /**
     * Show objective detail with KRs and check-ins.
     */
    public function show(Objective $objective, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($objective->owner_id !== $user->id && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $objective->load([
            'owner:id,name,avatar_path',
            'keyResults.checkIns.user:id,name',
            'children.keyResults',
            'children.owner:id,name,avatar_path',
        ]);

        return response()->json([
            'data' => $this->transformObjective($objective, true),
        ]);
    }

    /**
     * Create a new objective.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'type' => 'in:company,team,personal',
            'period' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'parent_id' => 'nullable|exists:objectives,id',
            'status' => 'in:draft,active,completed,cancelled',
        ]);

        $user = $request->user();
        $validated['owner_id'] = $user->id;
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['type'] = $validated['type'] ?? 'personal';
        $validated['period'] = $validated['period'] ?? Objective::currentPeriod();
        $validated['progress'] = 0;

        $objective = Objective::create($validated);
        $objective->load(['owner:id,name,avatar_path', 'keyResults']);

        return response()->json([
            'message' => 'Objective created.',
            'data' => $this->transformObjective($objective),
        ], 201);
    }

    /**
     * Update an objective.
     */
    public function update(Request $request, Objective $objective): JsonResponse
    {
        $user = $request->user();
        if ($objective->owner_id !== $user->id && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'type' => 'in:company,team,personal',
            'status' => 'in:draft,active,completed,cancelled',
            'period' => 'nullable|string|max:20',
        ]);

        $objective->update($validated);
        $objective->load(['owner:id,name,avatar_path', 'keyResults']);

        return response()->json([
            'message' => 'Objective updated.',
            'data' => $this->transformObjective($objective),
        ]);
    }

    /**
     * Delete an objective.
     */
    public function destroy(Objective $objective, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($objective->owner_id !== $user->id && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $objective->keyResults()->each(fn($kr) => $kr->checkIns()->delete());
        $objective->keyResults()->delete();
        $objective->delete();

        return response()->json(['message' => 'Objective deleted.']);
    }

    /**
     * Add a key result to an objective.
     */
    public function storeKeyResult(Request $request, Objective $objective): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'metric_type' => 'in:number,percentage,currency,boolean',
            'start_value' => 'nullable|numeric',
            'target_value' => 'required|numeric',
            'weight' => 'nullable|integer|min:1|max:100',
        ]);

        $validated['sort_order'] = $objective->keyResults()->count();
        $validated['current_value'] = $validated['start_value'] ?? 0;
        $validated['metric_type'] = $validated['metric_type'] ?? 'number';
        $validated['weight'] = $validated['weight'] ?? 1;

        $kr = $objective->keyResults()->create($validated);

        return response()->json([
            'message' => 'Key Result added.',
            'data' => $this->transformKeyResult($kr),
        ], 201);
    }

    /**
     * Check-in: update a KR's current value.
     */
    public function checkIn(Request $request, KeyResult $keyResult): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|numeric',
            'note' => 'nullable|string|max:500',
        ]);

        $previousValue = $keyResult->current_value;

        OkrCheckIn::create([
            'key_result_id' => $keyResult->id,
            'user_id' => $request->user()->id,
            'previous_value' => $previousValue,
            'new_value' => $validated['value'],
            'note' => $validated['note'] ?? null,
        ]);

        $keyResult->update(['current_value' => $validated['value']]);

        // Recalculate parent objective progress
        $keyResult->objective->recalculateProgress();

        $keyResult->load('checkIns.user:id,name');

        return response()->json([
            'message' => 'Check-in recorded.',
            'data' => $this->transformKeyResult($keyResult),
        ]);
    }

    // ── Transform helpers ──────────────────────────────

    private function transformObjective(Objective $obj, bool $detailed = false): array
    {
        $data = [
            'id' => $obj->id,
            'title' => $obj->title,
            'description' => $obj->description,
            'type' => $obj->type,
            'type_color' => $obj->type_color,
            'period' => $obj->period,
            'department' => $obj->department,
            'status' => $obj->status,
            'progress' => $obj->progress,
            'owner' => $obj->relationLoaded('owner') && $obj->owner ? [
                'id' => $obj->owner->id,
                'name' => $obj->owner->name,
                'avatar_url' => $obj->owner->avatar_url,
            ] : null,
            'key_results_count' => $obj->relationLoaded('keyResults') ? $obj->keyResults->count() : 0,
            'created_at' => $obj->created_at?->toIso8601String(),
        ];

        if ($obj->relationLoaded('keyResults')) {
            $data['key_results'] = $obj->keyResults->map(fn($kr) => $this->transformKeyResult($kr))->values();
        }

        if ($detailed) {
            $data['children'] = $obj->relationLoaded('children')
                ? $obj->children->map(fn($c) => $this->transformObjective($c))->values()
                : [];
        }

        return $data;
    }

    private function transformKeyResult(KeyResult $kr): array
    {
        return [
            'id' => $kr->id,
            'title' => $kr->title,
            'metric_type' => $kr->metric_type,
            'start_value' => (float) $kr->start_value,
            'target_value' => (float) $kr->target_value,
            'current_value' => (float) $kr->current_value,
            'progress' => $kr->progress,
            'weight' => $kr->weight,
            'sort_order' => $kr->sort_order,
            'check_ins' => $kr->relationLoaded('checkIns')
                ? $kr->checkIns->map(fn($ci) => [
                    'id' => $ci->id,
                    'previous_value' => (float) $ci->previous_value,
                    'new_value' => (float) $ci->new_value,
                    'note' => $ci->note,
                    'user' => $ci->relationLoaded('user') && $ci->user ? [
                        'id' => $ci->user->id,
                        'name' => $ci->user->name,
                    ] : null,
                    'created_at' => $ci->created_at?->toIso8601String(),
                ])->values()
                : [],
        ];
    }
}
