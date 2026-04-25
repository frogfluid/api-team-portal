<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-admin-attendance');

        $q = AttendanceRecord::query()
            ->with('user:id,name')
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('user_id')) {
            $q->where('user_id', (int) $request->user_id);
        }
        if ($request->filled('from')) {
            $q->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('date', '<=', $request->to);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        $page = $q->paginate($request->integer('per_page', 50));

        return response()->json([
            'records' => $page->items(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
        ]);
    }
}
