<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\JobScopeAssignUsersRequest;
use App\Http\Requests\Admin\JobScopeStoreRequest;
use App\Http\Requests\Admin\JobScopeUpdateRequest;
use App\Models\JobScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobScopeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->user()?->role !== UserRole::ADMIN) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        $q = JobScope::query()->with('users:id,name')->orderByDesc('created_at');

        return response()->json(['job_scopes' => $q->paginate($request->integer('per_page', 50))]);
    }

    public function store(JobScopeStoreRequest $request): JsonResponse
    {
        $scope = JobScope::create($request->validated());

        return response()->json(['job_scope' => $scope], 201);
    }

    public function update(JobScopeUpdateRequest $request, JobScope $jobScope): JsonResponse
    {
        $jobScope->update($request->validated());

        return response()->json(['job_scope' => $jobScope->fresh()]);
    }

    public function destroy(Request $request, JobScope $jobScope): JsonResponse
    {
        $this->authorize('delete', $jobScope);
        $jobScope->delete();

        return response()->json(['ok' => true]);
    }

    public function assignUsers(JobScopeAssignUsersRequest $request, JobScope $jobScope): JsonResponse
    {
        $jobScope->users()->sync($request->validated()['user_ids']);

        return response()->json(['job_scope' => $jobScope->load('users:id,name')]);
    }
}
