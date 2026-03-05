<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $announcements = Announcement::with('author:id,name,avatar_path')
            ->active()
            ->orderBy('pinned', 'desc')
            ->latest('published_at')
            ->paginate(20);

        return response()->json([
            'data' => $announcements->map(fn($a) => $this->transform($a)),
            'meta' => [
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'total' => $announcements->total(),
            ],
        ]);
    }

    /**
     * Admin: list all announcements (including expired).
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $announcements = Announcement::with('author:id,name,avatar_path')
            ->orderBy('pinned', 'desc')
            ->latest('published_at')
            ->paginate(20);

        return response()->json([
            'data' => $announcements->map(fn($a) => $this->transform($a)),
            'meta' => [
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'total' => $announcements->total(),
            ],
        ]);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $announcement->load('author:id,name,avatar_path');
        return response()->json(['data' => $this->transform($announcement)]);
    }

    /**
     * Admin: create a new announcement.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|in:info,warning,urgent',
            'pinned' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        $validated['author_id'] = $request->user()->id;
        $validated['published_at'] = $validated['published_at'] ?? now();
        $validated['pinned'] = $validated['pinned'] ?? false;
        $validated['type'] = $validated['type'] ?? 'info';

        $announcement = Announcement::create($validated);
        $announcement->load('author:id,name,avatar_path');

        return response()->json(['data' => $this->transform($announcement)], 201);
    }

    /**
     * Admin: update an announcement.
     */
    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'type' => 'nullable|in:info,warning,urgent',
            'pinned' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        $announcement->update($validated);
        $announcement->load('author:id,name,avatar_path');

        return response()->json(['data' => $this->transform($announcement)]);
    }

    /**
     * Admin: delete an announcement.
     */
    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();
        return response()->json(['message' => 'Announcement deleted']);
    }

    private function transform(Announcement $a): array
    {
        $typeMap = [
            'urgent' => ['label' => 'Urgent', 'color' => '#EF4444'],
            'warning' => ['label' => 'Warning', 'color' => '#F59E0B'],
            'info' => ['label' => 'Info', 'color' => '#3B82F6'],
        ];
        $badge = $typeMap[$a->type] ?? $typeMap['info'];

        return [
            'id' => $a->id,
            'title' => $a->title,
            'body' => $a->body,
            'type' => $a->type ?? 'info',
            'type_label' => $badge['label'],
            'type_color' => $badge['color'],
            'pinned' => $a->pinned,
            'published_at' => $a->published_at?->toIso8601String(),
            'expires_at' => $a->expires_at?->toIso8601String(),
            'author' => $a->relationLoaded('author') && $a->author ? [
                'id' => $a->author->id,
                'name' => $a->author->name,
                'avatar_url' => $a->author->avatar_url,
            ] : null,
        ];
    }
}
