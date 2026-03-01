<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkDailyLog;
use App\Models\WorkSchedule;
use App\Models\WeeklyReport;
use App\Services\LeaveQuotaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->canReview()) {
            abort(403, 'Unauthorized.');
        }

        $pendingDaily = WorkDailyLog::where('status', 'submitted')
            ->with('user:id,name')
            ->orderByDesc('work_date')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'type' => 'daily_log',
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'schedule_type' => null,
                'start_at' => $log->work_date?->toDateString(),
                'end_at' => null,
                'note' => $log->note,
                'status' => $log->status,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        $pendingWeekly = WeeklyReport::where('status', 'submitted')
            ->with('user:id,name')
            ->orderByDesc('week_start_date')
            ->limit(50)
            ->get()
            ->map(fn ($report) => [
                'id' => $report->id,
                'type' => 'weekly_report',
                'user_id' => $report->user_id,
                'user_name' => $report->user?->name,
                'schedule_type' => null,
                'start_at' => $report->week_start_date?->toDateString(),
                'end_at' => null,
                'note' => $report->summary,
                'status' => $report->status,
                'created_at' => $report->created_at?->toIso8601String(),
            ]);

        $pendingSchedules = WorkSchedule::where('status', 'pending')
            ->with('user:id,name')
            ->orderByDesc('start_at')
            ->limit(50)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'type' => 'schedule',
                'user_id' => $s->user_id,
                'user_name' => $s->user?->name,
                'schedule_type' => $s->type,
                'start_at' => $s->start_at?->toIso8601String(),
                'end_at' => $s->end_at?->toIso8601String(),
                'note' => $s->note,
                'status' => $s->status,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'pending_leaves' => $pendingSchedules->values(),
            'pending_daily_logs' => $pendingDaily->values(),
            'pending_weekly_reports' => $pendingWeekly->values(),
        ]);
    }

    public function approveSchedule(Request $request, WorkSchedule $workSchedule): JsonResponse
    {
        if (!$request->user()->canReview()) abort(403);

        if ($workSchedule->status !== 'pending') {
            return response()->json(['message' => 'Schedule is not pending.'], 422);
        }

        $workSchedule->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // If it's a leave, apply quota deduction
        if ($workSchedule->type === 'leave') {
            $leaveQuotaService = app(LeaveQuotaService::class);
            $leaveType = $workSchedule->leave_type ?? 'annual';
            $timezone = data_get($workSchedule->user->preferences ?? [], 'workspace.timezone', 'Asia/Tokyo');
            $year = $workSchedule->start_at ? Carbon::parse($workSchedule->start_at)->timezone($timezone)->year : now()->year;

            $quota = $leaveQuotaService->ensureQuota($workSchedule->user, $year);
            $field = $leaveType === 'sick' ? 'sick_used' : 'annual_used';
            $quota->increment($field, (float) $workSchedule->leave_days);
        }

        return response()->json(['message' => 'Schedule approved.']);
    }

    public function rejectSchedule(Request $request, WorkSchedule $workSchedule): JsonResponse
    {
        if (!$request->user()->canReview()) abort(403);

        if ($workSchedule->status !== 'pending') {
            return response()->json(['message' => 'Schedule is not pending.'], 422);
        }

        $workSchedule->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['message' => 'Schedule rejected.']);
    }

    public function approveDaily(Request $request, WorkDailyLog $dailyLog): JsonResponse
    {
        if (!$request->user()->canReview()) abort(403);

        $dailyLog->update(['status' => 'approved']);

        return response()->json(['message' => 'Daily log approved.']);
    }

    public function rejectDaily(Request $request, WorkDailyLog $dailyLog): JsonResponse
    {
        if (!$request->user()->canReview()) abort(403);

        $dailyLog->update(['status' => 'rejected']);

        return response()->json(['message' => 'Daily log rejected.']);
    }

    public function approveWeekly(Request $request, WeeklyReport $weeklyReport): JsonResponse
    {
        if (!$request->user()->canReview()) abort(403);

        $weeklyReport->update(['status' => 'approved']);

        return response()->json(['message' => 'Weekly report approved.']);
    }

    public function rejectWeekly(Request $request, WeeklyReport $weeklyReport): JsonResponse
    {
        if (!$request->user()->canReview()) abort(403);

        $weeklyReport->update(['status' => 'rejected']);

        return response()->json(['message' => 'Weekly report rejected.']);
    }
}
