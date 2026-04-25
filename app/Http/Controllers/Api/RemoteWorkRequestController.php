<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RemoteWorkRequestStoreRequest;
use App\Http\Requests\RemoteWorkRequestUpdateRequest;
use App\Http\Requests\RemoteWorkRequestRejectRequest;
use App\Models\RemoteWorkRequest;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteWorkRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isReviewer = $user->role instanceof UserRole && $user->role->canReview();

        $q = RemoteWorkRequest::query()->with('user:id,name')->orderByDesc('created_at');
        if (! $isReviewer) {
            $q->where('user_id', $user->id);
        }
        if (! $request->boolean('force_full') && $request->filled('updated_since')) {
            try {
                $q->where('updated_at', '>', \Carbon\Carbon::parse((string) $request->updated_since));
            } catch (\Exception $e) {}
        }

        return response()->json(['requests' => $q->get()]);
    }

    public function show(Request $request, RemoteWorkRequest $remoteWorkRequest): JsonResponse
    {
        $this->authorize('view', $remoteWorkRequest);
        return response()->json(['request' => $remoteWorkRequest->load('user:id,name', 'approver:id,name')]);
    }

    public function store(RemoteWorkRequestStoreRequest $request): JsonResponse
    {
        $req = RemoteWorkRequest::create([
            'user_id' => $request->user()->id,
            'status' => 'pending',
            ...$request->validated(),
        ]);
        return response()->json(['request' => $req], 201);
    }

    public function update(RemoteWorkRequestUpdateRequest $request, RemoteWorkRequest $remoteWorkRequest): JsonResponse
    {
        $this->authorize('update', $remoteWorkRequest);
        $remoteWorkRequest->update($request->validated());
        return response()->json(['request' => $remoteWorkRequest]);
    }

    public function destroy(Request $request, RemoteWorkRequest $remoteWorkRequest): JsonResponse
    {
        $this->authorize('delete', $remoteWorkRequest);
        $remoteWorkRequest->delete();
        return response()->json(['ok' => true]);
    }

    public function approve(Request $request, RemoteWorkRequest $remoteWorkRequest): JsonResponse
    {
        $this->authorize('approve', $remoteWorkRequest);
        $remoteWorkRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        return response()->json(['request' => $remoteWorkRequest->fresh()]);
    }

    public function reject(RemoteWorkRequestRejectRequest $request, RemoteWorkRequest $remoteWorkRequest): JsonResponse
    {
        $this->authorize('reject', $remoteWorkRequest);
        $remoteWorkRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $request->string('rejection_reason')->value(),
        ]);
        return response()->json(['request' => $remoteWorkRequest->fresh()]);
    }
}
