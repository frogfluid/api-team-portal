<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveQuota;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Notifications\SystemMessageNotification;
use App\Services\LeaveQuotaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LeaveController extends Controller
{
    public function __construct(private readonly LeaveQuotaService $leaveQuotaService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = data_get($user->preferences, 'workspace.timezone', 'Asia/Tokyo');
        $year = $request->integer('year', Carbon::now($timezone)->year);

        $query = WorkSchedule::query()
            ->where('user_id', $user->id)
            ->where('type', 'leave')
            ->with('approver:id,name')
            ->orderByDesc('start_at')
            ->limit(50);

        if (! $request->boolean('force_full') && $request->filled('updated_since')) {
            try {
                $since = \Carbon\Carbon::parse((string) $request->updated_since);
                $query->where('updated_at', '>', $since);
            } catch (\Exception $e) {
                // Malformed timestamp — fall through to full list.
            }
        }

        $leaves = $query->get();

        return response()->json($leaves->map(fn($l) => $this->transformLeave($l, $timezone))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'leave_type' => ['required', 'string', 'in:annual,sick'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $timezone = data_get($user->preferences, 'workspace.timezone', 'Asia/Tokyo');

        $startLocal = Carbon::parse($request->start_date, $timezone)->startOfDay();
        $endLocal = Carbon::parse($request->end_date, $timezone)->endOfDay();
        $leaveDays = (float) ($startLocal->copy()->startOfDay()->diffInDays($endLocal->copy()->startOfDay()) + 1);
        $leaveType = $request->leave_type;

        $startUtc = $startLocal->copy()->utc();
        $endUtc = $endLocal->copy()->utc();

        $leaveYear = (int) $startLocal->year;
        $remaining = $this->leaveQuotaService->remainingDays($user, $leaveYear, $leaveType);
        if ($leaveDays > $remaining) {
            throw ValidationException::withMessages(['start_date' => 'Insufficient leave balance.']);
        }

        $schedule = WorkSchedule::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'leave_type' => $leaveType,
            'leave_days' => $leaveDays,
            'all_day' => true,
            'start_at' => $startUtc,
            'end_at' => $endUtc,
            'note' => trim((string) $request->note),
            'status' => 'pending',
        ]);

        $reviewers = User::where('is_active', true)->whereIn('role', ['admin', 'manager'])->get();
        Notification::send($reviewers, new SystemMessageNotification(
            'New Leave Request',
            "{$user->name} submitted a leave request.",
            ''
        ));

        return response()->json($this->transformLeave($schedule, $timezone), 201);
    }

    public function update(Request $request, WorkSchedule $leave): JsonResponse
    {
        if ($leave->type !== 'leave')
            abort(404);
        $this->authorize('update', $leave);

        $request->validate([
            'leave_type' => ['required', 'string', 'in:annual,sick'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $timezone = data_get($user->preferences, 'workspace.timezone', 'Asia/Tokyo');

        $startLocal = Carbon::parse($request->start_date, $timezone)->startOfDay();
        $endLocal = Carbon::parse($request->end_date, $timezone)->endOfDay();
        $leaveDays = (float) ($startLocal->copy()->startOfDay()->diffInDays($endLocal->copy()->startOfDay()) + 1);

        DB::transaction(function () use ($leave, $request, $startLocal, $endLocal, $leaveDays) {
            if ($leave->status === 'approved') {
                $this->leaveQuotaService->rollbackApproval($leave);
            }

            $leave->update([
                'leave_type' => $request->leave_type,
                'leave_days' => $leaveDays,
                'start_at' => $startLocal->copy()->utc(),
                'end_at' => $endLocal->copy()->utc(),
                'note' => trim((string) $request->note),
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ]);
        });

        $timezone = data_get($request->user()->preferences, 'workspace.timezone', 'Asia/Tokyo');
        return response()->json($this->transformLeave($leave->fresh()->load('approver:id,name'), $timezone));
    }

    public function destroy(Request $request, WorkSchedule $leave): JsonResponse
    {
        if ($leave->type !== 'leave')
            abort(404);
        $this->authorize('delete', $leave);

        DB::transaction(function () use ($leave) {
            if ($leave->status === 'approved') {
                $this->leaveQuotaService->rollbackApproval($leave);
            }
            $leave->delete();
        });

        return response()->json(['message' => 'Leave request cancelled.']);
    }

    public function quota(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = data_get($user->preferences, 'workspace.timezone', 'Asia/Tokyo');
        $year = $request->integer('year', Carbon::now($timezone)->year);

        $quota = $this->leaveQuotaService->ensureQuota($user, $year);

        return response()->json([
            'user_id' => $user->id,
            'year' => $year,
            'annual_total' => (float) $quota->annual_total,
            'annual_used' => (float) $quota->annual_used,
            'sick_total' => (float) $quota->sick_total,
            'sick_used' => (float) $quota->sick_used,
        ]);
    }

    public function approve(Request $request, WorkSchedule $leave): JsonResponse
    {
        if ($leave->type !== 'leave' || $leave->status !== 'pending') {
            return response()->json(['message' => 'Invalid leave request.'], 422);
        }

        $leave->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // Apply quota deduction
        $leaveType = $leave->leave_type ?? 'annual';
        $timezone = data_get($leave->user->preferences ?? [], 'workspace.timezone', 'Asia/Tokyo');
        $year = $leave->start_at ? Carbon::parse($leave->start_at)->timezone($timezone)->year : now()->year;

        $quota = $this->leaveQuotaService->ensureQuota($leave->user, $year);
        $field = $leaveType === 'sick' ? 'sick_used' : 'annual_used';
        $quota->increment($field, (float) $leave->leave_days);

        return response()->json(['success' => true, 'message' => 'Leave approved.', 'data' => null]);
    }

    public function reject(Request $request, WorkSchedule $leave): JsonResponse
    {
        if ($leave->type !== 'leave' || $leave->status !== 'pending') {
            return response()->json(['message' => 'Invalid leave request.'], 422);
        }

        $leave->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Leave rejected.', 'data' => null]);
    }

    private function transformLeave(WorkSchedule $leave, string $timezone = 'Asia/Tokyo'): array
    {
        return [
            'id' => $leave->id,
            'user_id' => $leave->user_id,
            'leave_type' => $leave->leave_type ?? 'annual',
            'start_date' => $leave->start_at ? Carbon::parse($leave->start_at)->timezone($timezone)->toDateString() : null,
            'end_date' => $leave->end_at ? Carbon::parse($leave->end_at)->timezone($timezone)->toDateString() : null,
            'leave_days' => (float) ($leave->leave_days ?? 0),
            'status' => $leave->status,
            'note' => $leave->note,
            'approver_name' => $leave->approver?->name,
            'created_at' => $leave->created_at?->toIso8601String(),
        ];
    }
}
