<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkDeliverable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliverableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = WorkDeliverable::with(['user:id,name,avatar_path', 'project:id,name', 'task:id,title']);

        if (!$user->canAccessAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $items = $query->latest()->paginate(20);

        return response()->json([
            'data' => $items->map(fn($d) => $this->transform($d)),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'description' => 'nullable|string|max:2000',
            'type' => 'required|in:design,code,document,video,presentation,other',
            'task_id' => 'nullable|exists:tasks,id',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $validated['user_id'] = $request->user()->id;
        $deliverable = WorkDeliverable::create($validated);
        $deliverable->load(['user:id,name,avatar_path', 'project:id,name', 'task:id,title']);

        return response()->json([
            'message' => 'Deliverable added.',
            'data' => $this->transform($deliverable),
        ], 201);
    }

    public function update(Request $request, WorkDeliverable $deliverable): JsonResponse
    {
        $user = $request->user();
        if ($deliverable->user_id !== $user->id && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:500',
            'description' => 'nullable|string|max:2000',
            'type' => 'sometimes|in:design,code,document,video,presentation,other',
        ]);

        $deliverable->update($validated);
        $deliverable->load(['user:id,name,avatar_path', 'project:id,name', 'task:id,title']);

        return response()->json(['message' => 'Deliverable updated.', 'data' => $this->transform($deliverable)]);
    }

    public function destroy(Request $request, WorkDeliverable $deliverable): JsonResponse
    {
        $user = $request->user();
        if ($deliverable->user_id !== $user->id && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $deliverable->delete();
        return response()->json(['message' => 'Deliverable deleted.']);
    }

    private function transform(WorkDeliverable $d): array
    {
        $meta = $d->type_meta;
        return [
            'id' => $d->id,
            'title' => $d->title,
            'url' => $d->url,
            'description' => $d->description,
            'type' => $d->type,
            'type_label' => $meta['label'],
            'type_color' => $meta['color'],
            'user' => $d->relationLoaded('user') && $d->user ? [
                'id' => $d->user->id,
                'name' => $d->user->name,
                'avatar_url' => $d->user->avatar_url,
            ] : null,
            'project' => $d->relationLoaded('project') && $d->project ? [
                'id' => $d->project->id,
                'name' => $d->project->name,
            ] : null,
            'task' => $d->relationLoaded('task') && $d->task ? [
                'id' => $d->task->id,
                'title' => $d->task->title,
            ] : null,
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
