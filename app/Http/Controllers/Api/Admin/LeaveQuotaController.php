<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveQuota;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveQuotaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->canManageUsers()) abort(403);

        $year = $request->integer('year', Carbon::now()->year);

        $quotas = LeaveQuota::where('year', $year)
            ->with('user:id,name,email')
            ->orderBy('user_id')
            ->get();

        return response()->json($quotas);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->canManageUsers()) abort(403);

        $request->validate([
            'year' => ['sometimes', 'integer'],
            'annual_total' => ['sometimes', 'numeric', 'min:0'],
            'sick_total' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $year = $request->integer('year', Carbon::now()->year);

        $quota = LeaveQuota::firstOrCreate(
            ['user_id' => $user->id, 'year' => $year],
            ['annual_total' => 10, 'annual_used' => 0, 'sick_total' => 5, 'sick_used' => 0]
        );

        if ($request->has('annual_total')) $quota->annual_total = $request->annual_total;
        if ($request->has('sick_total')) $quota->sick_total = $request->sick_total;
        $quota->save();

        return response()->json(['message' => 'Quota updated.', 'quota' => $quota]);
    }
}
