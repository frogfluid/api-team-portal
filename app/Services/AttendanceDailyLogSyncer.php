<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\WorkDailyLog;
use Illuminate\Support\Carbon;

class AttendanceDailyLogSyncer
{
    /**
     * Sync attendance clock times into the matching WorkDailyLog row.
     * Creates a draft daily log if none exists. Recomputes worked_minutes
     * whenever both started_at and ended_at are set, subtracting break_minutes.
     *
     * Called from both employee-facing clock actions and admin-facing edits
     * so the two paths can never drift apart on daily-log semantics.
     */
    public function sync(AttendanceRecord $record): void
    {
        if (!$record->user_id || !$record->date) {
            return;
        }

        $normalizedDate = Carbon::parse($record->date)->toDateString();

        $log = WorkDailyLog::query()
            ->where('user_id', $record->user_id)
            ->whereDate('work_date', $normalizedDate)
            ->first();

        if (!$log) {
            $log = WorkDailyLog::create([
                'user_id' => $record->user_id,
                'work_date' => $normalizedDate,
                'break_minutes' => 0,
                'status' => 'draft',
            ]);
        }

        $log->started_at = $record->clock_in_at;
        $log->ended_at = $record->clock_out_at;

        if ($log->started_at && $log->ended_at) {
            $minutes = Carbon::parse($log->started_at)->diffInMinutes(Carbon::parse($log->ended_at));
            $log->worked_minutes = max(0, $minutes - (int) $log->break_minutes);
        } else {
            $log->worked_minutes = 0;
        }

        $log->save();
    }
}
