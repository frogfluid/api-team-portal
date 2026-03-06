<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\WorkDailyLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Get attendance records for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AttendanceRecord::forUser($user->id);

        if ($request->filled('month')) {
            $query->whereMonth('date', substr($request->month, 5))
                ->whereYear('date', substr($request->month, 0, 4));
        }

        $records = $query->latest('date')->paginate(31);

        return response()->json([
            'data' => $records->map(fn($r) => $this->transform($r)),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Get today's attendance status.
     */
    public function today(Request $request): JsonResponse
    {
        $record = AttendanceRecord::forUser($request->user()->id)
            ->today()
            ->first();

        return response()->json([
            'success' => true,
            'data' => $record ? $this->transform($record) : null,
            'is_clocked_in' => $record?->clock_in_at !== null,
            'is_clocked_out' => $record?->clock_out_at !== null,
        ]);
    }

    /**
     * Clock in / Clock out.
     */
    public function clockAction(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();
        $record = AttendanceRecord::forUser($user->id)->forDate($today)->first();

        if (!$record) {
            // Clock in
            $record = AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => $today,
                'clock_in_at' => now(),
                'clock_in_ip' => $request->ip(),
                'status' => now()->hour >= 10 ? 'late' : 'normal',
            ]);

            // Sync daily log started_at
            $this->syncDailyLog($user->id, $today, 'started_at', $record->clock_in_at);

            return response()->json([
                'success' => true,
                'message' => 'Clocked in successfully.',
                'data' => $this->transform($record),
            ]);
        }

        if ($record->clock_out_at) {
            return response()->json(['success' => false, 'message' => 'Already clocked out today.', 'data' => null], 422);
        }

        // Clock out
        $clockOutTime = now();
        $durationMinutes = min(1440, max(0, (int) $clockOutTime->diffInMinutes($record->clock_in_at)));
        $status = $record->status;

        // Early leave if before 18:00 and normal status
        if ($clockOutTime->hour < 18 && $status === 'normal') {
            $status = 'early_leave';
        }

        $record->update([
            'clock_out_at' => $clockOutTime,
            'clock_out_ip' => $request->ip(),
            'work_duration_minutes' => $durationMinutes,
            'status' => $status,
        ]);

        // Sync daily log ended_at + worked_minutes
        $this->syncDailyLog($user->id, $today, 'ended_at', $clockOutTime, $record->clock_in_at);

        return response()->json([
            'success' => true,
            'message' => 'Clocked out successfully.',
            'data' => $this->transform($record),
        ]);
    }

    private function transform(AttendanceRecord $r): array
    {
        return [
            'id' => $r->id,
            'date' => $r->date?->toDateString(),
            'clock_in_at' => $r->clock_in_at?->toIso8601String(),
            'clock_out_at' => $r->clock_out_at?->toIso8601String(),
            'work_duration_minutes' => $r->work_duration_minutes,
            'formatted_duration' => $r->formatted_duration,
            'status' => $r->status,
            'status_color' => $r->status_color,
            'note' => $r->note,
        ];
    }

    /**
     * Sync attendance time to the daily log (ported from web AttendanceController).
     */
    private function syncDailyLog(int $userId, string $date, string $field, $time, $clockInAt = null): void
    {
        $log = WorkDailyLog::firstOrCreate(
            ['user_id' => $userId, 'work_date' => $date],
            ['break_minutes' => 0, 'status' => 'draft']
        );

        $log->{$field} = $time;

        // Recalc worked_minutes if both times exist
        if ($log->started_at && $log->ended_at) {
            $minutes = Carbon::parse($log->started_at)->diffInMinutes(Carbon::parse($log->ended_at));
            $log->worked_minutes = max(0, $minutes - (int) $log->break_minutes);
        }

        $log->save();
    }
}
