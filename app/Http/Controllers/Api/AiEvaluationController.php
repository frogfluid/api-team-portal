<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AiEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiEvaluationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->role === UserRole::ADMIN;

        $q = AiEvaluation::query()->orderByDesc('created_at');
        if (! $isAdmin) {
            $q->where('user_id', $user->id);
        }
        if (! $request->boolean('force_full') && $request->filled('updated_since')) {
            try {
                $q->where('updated_at', '>', \Carbon\Carbon::parse((string) $request->updated_since));
            } catch (\Exception $e) {
            }
        }

        return response()->json(['evaluations' => $q->get()]);
    }

    public function show(Request $request, AiEvaluation $evaluation): JsonResponse
    {
        $this->authorize('view', $evaluation);

        return response()->json(['evaluation' => $evaluation]);
    }
}
