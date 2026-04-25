<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\JobScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobScopeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->role === UserRole::ADMIN;

        $q = JobScope::query()->with('users:id,name')->orderByDesc('created_at');
        if (! $isAdmin) {
            $q->whereHas('users', fn ($qq) => $qq->where('users.id', $user->id));
        }

        return response()->json(['job_scopes' => $q->get()]);
    }

    public function show(Request $request, JobScope $jobScope): JsonResponse
    {
        $this->authorize('view', $jobScope);

        return response()->json(['job_scope' => $jobScope->load('users:id,name')]);
    }
}
