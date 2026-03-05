<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * List projects accessible by the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Project::with(['owner:id,name,avatar_path', 'members:id,name,avatar_path'])
            ->accessibleBy($user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $projects = $query->latest()->paginate(20);

        return response()->json([
            'data' => $projects->map(fn($p) => $this->transformProject($p)),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    /**
     * Create a new project.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'status' => 'in:active,paused,completed,archived',
            'priority' => 'in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date|after_or_equal:start_date',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id',
        ]);

        $user = $request->user();
        $validated['owner_id'] = $user->id;
        $validated['created_by'] = $user->id;
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['priority'] = $validated['priority'] ?? 'medium';
        $validated['progress'] = 0;

        $members = $validated['members'] ?? [];
        unset($validated['members']);

        $project = Project::create($validated);

        // Attach members
        if (!empty($members)) {
            $memberData = [];
            foreach ($members as $memberId) {
                if ($memberId != $user->id) {
                    $memberData[$memberId] = ['role' => 'member'];
                }
            }
            $project->members()->attach($memberData);
        }

        $project->load(['owner:id,name,avatar_path', 'members:id,name,avatar_path', 'milestones']);

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => $this->transformProject($project, true),
        ], 201);
    }

    /**
     * Show project details with milestones, members, and task stats.
     */
    public function show(Project $project, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$project->isMember($user->id) && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $project->load([
            'owner:id,name,avatar_path',
            'creator:id,name',
            'members:id,name,avatar_path',
            'milestones',
            'tasks:id,project_id,title,status,priority,progress,due_at,owner_id',
            'tasks.owner:id,name,avatar_path',
        ]);

        return response()->json([
            'data' => $this->transformProject($project, true),
        ]);
    }

    /**
     * Update a project.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        if ($project->owner_id !== $user->id && $project->created_by !== $user->id && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'status' => 'in:active,paused,completed,archived',
            'priority' => 'in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id',
        ]);

        if (isset($validated['members'])) {
            $members = $validated['members'];
            unset($validated['members']);

            $memberData = [];
            foreach ($members as $memberId) {
                if ($memberId != $project->owner_id) {
                    $memberData[$memberId] = ['role' => 'member'];
                }
            }
            $project->members()->sync($memberData);
        }

        $project->update($validated);
        $project->load(['owner:id,name,avatar_path', 'members:id,name,avatar_path', 'milestones']);

        return response()->json([
            'message' => 'Project updated successfully.',
            'data' => $this->transformProject($project, true),
        ]);
    }

    /**
     * Delete a project.
     */
    public function destroy(Project $project, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($project->owner_id !== $user->id && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $project->milestones()->delete();
        $project->members()->detach();
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully.']);
    }

    // ── Milestone sub-resource ──────────────────────────────

    /**
     * Add a milestone to a project.
     */
    public function storeMilestone(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        if (!$project->isMember($user->id) && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'due_date' => 'nullable|date',
            'status' => 'in:open,completed',
        ]);

        $validated['sort_order'] = $project->milestones()->count();
        $validated['status'] = $validated['status'] ?? 'open';

        $milestone = $project->milestones()->create($validated);

        return response()->json([
            'message' => 'Milestone added.',
            'data' => $this->transformMilestone($milestone),
        ], 201);
    }

    /**
     * Update a milestone.
     */
    public function updateMilestone(Request $request, Project $project, Milestone $milestone): JsonResponse
    {
        if ($milestone->project_id !== $project->id) {
            return response()->json(['message' => 'Milestone does not belong to this project.'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'due_date' => 'nullable|date',
            'status' => 'in:open,completed',
            'sort_order' => 'nullable|integer',
        ]);

        $milestone->update($validated);

        return response()->json([
            'message' => 'Milestone updated.',
            'data' => $this->transformMilestone($milestone->fresh()),
        ]);
    }

    /**
     * Delete a milestone.
     */
    public function destroyMilestone(Request $request, Project $project, Milestone $milestone): JsonResponse
    {
        if ($milestone->project_id !== $project->id) {
            return response()->json(['message' => 'Milestone does not belong to this project.'], 404);
        }

        $milestone->delete();

        return response()->json(['message' => 'Milestone deleted.']);
    }

    // ── Transform helpers (matching TaskController pattern) ──

    private function transformProject(Project $project, bool $detailed = false): array
    {
        $data = [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'color' => $project->color,
            'icon' => $project->icon,
            'status' => $project->status,
            'priority' => $project->priority,
            'priority_color' => $project->priority_color,
            'progress' => $project->progress,
            'start_date' => $project->start_date?->toDateString(),
            'target_date' => $project->target_date?->toDateString(),
            'owner' => $project->relationLoaded('owner') && $project->owner ? [
                'id' => $project->owner->id,
                'name' => $project->owner->name,
                'avatar_url' => $project->owner->avatar_url,
            ] : null,
            'members' => $project->relationLoaded('members')
                ? $project->members->map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'avatar_url' => $m->avatar_url,
                    'role' => $m->pivot->role ?? 'member',
                ])->values()
                : [],
            'member_count' => $project->relationLoaded('members') ? $project->members->count() : 0,
            'task_stats' => $project->task_stats,
            'created_at' => $project->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['milestones'] = $project->relationLoaded('milestones')
                ? $project->milestones->map(fn($m) => $this->transformMilestone($m))->values()
                : [];

            $data['tasks'] = $project->relationLoaded('tasks')
                ? $project->tasks->map(fn($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'progress' => $t->progress,
                    'due_at' => $t->due_at?->toIso8601String(),
                    'owner' => $t->relationLoaded('owner') && $t->owner ? [
                        'id' => $t->owner->id,
                        'name' => $t->owner->name,
                        'avatar_url' => $t->owner->avatar_url,
                    ] : null,
                ])->values()
                : [];
        }

        return $data;
    }

    private function transformMilestone(Milestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'due_date' => $milestone->due_date?->toDateString(),
            'status' => $milestone->status,
            'sort_order' => $milestone->sort_order,
            'progress' => $milestone->progress,
            'is_overdue' => $milestone->isOverdue(),
            'created_at' => $milestone->created_at?->toIso8601String(),
        ];
    }
}
