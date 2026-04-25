<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\PayrollLockedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttendanceJudgePreviewRequest;
use App\Http\Requests\Admin\AttendanceStoreRequest;
use App\Http\Requests\Admin\AttendanceUpdateRequest;
use App\Models\AttendanceRecord;
use App\Models\Payroll;
use App\Services\AttendanceEditService;
use Carbon\Carbon;
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

    public function store(AttendanceStoreRequest $request, AttendanceEditService $service): JsonResponse
    {
        $validated = $request->validated();

        $this->assertPayrollNotLocked((int) $validated['user_id'], $validated['date']);

        $data = array_merge(['note' => null, 'post_payroll' => false], $validated);
        $record = $service->create($request->user(), $data);

        return response()->json(['record' => $record], 201);
    }

    public function update(AttendanceUpdateRequest $request, AttendanceRecord $attendance, AttendanceEditService $service): JsonResponse
    {
        $validated = $request->validated();

        $userId = (int) ($validated['user_id'] ?? $attendance->user_id);
        $date = $validated['date'] ?? $attendance->date;
        $this->assertPayrollNotLocked($userId, $date);

        // The service rewrites all editable fields from the data array, so merge
        // with the record's current values for any fields the request omitted.
        $merged = array_merge([
            'user_id' => $attendance->user_id,
            'date' => $attendance->date instanceof \DateTimeInterface
                ? $attendance->date->format('Y-m-d')
                : $attendance->date,
            'clock_in_at' => $attendance->clock_in_at,
            'clock_out_at' => $attendance->clock_out_at,
            'status' => $attendance->status,
            'note' => $attendance->note,
            'post_payroll' => false,
        ], $validated);

        $record = $service->update($request->user(), $attendance, $merged);

        return response()->json(['record' => $record]);
    }

    public function judgePreview(AttendanceJudgePreviewRequest $request, AttendanceEditService $service): JsonResponse
    {
        $data = array_merge(['note' => null, 'post_payroll' => false], $request->validated());
        $preview = $service->preview($data);

        return response()->json(['preview' => $preview]);
    }

    private function assertPayrollNotLocked(int $userId, $date): void
    {
        $ym = Carbon::parse($date)->format('Y-m');
        $locked = Payroll::where('user_id', $userId)
            ->where('year_month', $ym)
            ->where('status', 'paid')
            ->exists();
        if ($locked) {
            throw new PayrollLockedException("Payroll for {$ym} is paid; attendance edits are locked");
        }
    }
}
