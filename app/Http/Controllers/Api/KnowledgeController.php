<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KnowledgeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KnowledgeDocument::with('uploader:id,name,avatar_path');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $docs = $query->latest()->paginate(20);

        return response()->json([
            'data' => $docs->map(fn($d) => $this->transform($d)),
            'meta' => [
                'current_page' => $docs->currentPage(),
                'last_page' => $docs->lastPage(),
                'total' => $docs->total(),
            ],
            'categories' => KnowledgeDocument::categories(),
        ]);
    }

    public function show(KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        $knowledgeDocument->load('uploader:id,name,avatar_path');
        return response()->json(['data' => $this->transform($knowledgeDocument)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|in:policies,procedures,templates,training,general',
            'file' => 'required|file|max:20480',
        ]);

        $file = $request->file('file');
        $path = $file->store('knowledge', 'public');

        $doc = KnowledgeDocument::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        $doc->load('uploader:id,name,avatar_path');
        return response()->json(['message' => 'Document uploaded.', 'data' => $this->transform($doc)], 201);
    }

    public function update(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        $user = $request->user();
        if ($knowledgeDocument->uploaded_by !== $user->id && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'sometimes|in:policies,procedures,templates,training,general',
        ]);

        $knowledgeDocument->update($validated);
        $knowledgeDocument->load('uploader:id,name,avatar_path');
        return response()->json(['message' => 'Document updated.', 'data' => $this->transform($knowledgeDocument)]);
    }

    public function destroy(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        $user = $request->user();
        if ($knowledgeDocument->uploaded_by !== $user->id && !$user->canAccessAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($knowledgeDocument->file_path) {
            Storage::disk('public')->delete($knowledgeDocument->file_path);
        }
        $knowledgeDocument->delete();
        return response()->json(['message' => 'Document deleted.']);
    }

    public function download(KnowledgeDocument $knowledgeDocument)
    {
        if (!Storage::disk('public')->exists($knowledgeDocument->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return Storage::disk('public')->download($knowledgeDocument->file_path, $knowledgeDocument->file_name);
    }

    private function transform(KnowledgeDocument $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'description' => $d->description,
            'category' => $d->category,
            'file_name' => $d->file_name,
            'file_size' => $d->file_size,
            'file_size_formatted' => $d->file_size_formatted,
            'file_type' => $d->file_type,
            'icon' => $d->icon,
            'uploader' => $d->relationLoaded('uploader') && $d->uploader ? [
                'id' => $d->uploader->id,
                'name' => $d->uploader->name,
                'avatar_url' => $d->uploader->avatar_url,
            ] : null,
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
