<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiEvaluationStoreRequest;
use App\Http\Requests\Admin\AiEvaluationUpdateRequest;
use App\Models\AiEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiEvaluationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->user()?->role !== UserRole::ADMIN) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        $q = AiEvaluation::query()->with('user:id,name')->orderByDesc('created_at');

        return response()->json(['evaluations' => $q->paginate($request->integer('per_page', 50))]);
    }

    public function store(AiEvaluationStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['evaluator_id'] = $data['evaluator_id'] ?? $request->user()->id;

        $eval = AiEvaluation::create($data);

        return response()->json(['evaluation' => $eval], 201);
    }

    public function update(AiEvaluationUpdateRequest $request, AiEvaluation $evaluation): JsonResponse
    {
        $this->authorize('update', $evaluation);
        $evaluation->update($request->validated());

        return response()->json(['evaluation' => $evaluation->fresh()]);
    }
}
