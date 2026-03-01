<?php

namespace App\Services;

use App\Models\LeaveQuota;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Carbon;

class LeaveQuotaService
{
    public function ensureQuota(User $user, int $year): LeaveQuota
    {
        return LeaveQuota::firstOrCreate(
            [
                'user_id' => $user->id,
                'year' => $year,
            ],
            [
                'annual_total' => 0,
                'annual_used' => 0,
                'sick_total' => 0,
                'sick_used' => 0,
            ]
        );
    }

    public function remainingDays(User $user, int $year, string $leaveType): float
    {
        $quota = $this->ensureQuota($user, $year);

        if ($leaveType === 'sick') {
            return max(0.0, (float) $quota->sick_total - (float) $quota->sick_used);
        }

        return max(0.0, (float) $quota->annual_total - (float) $quota->annual_used);
    }

    public function applyApproval(WorkSchedule $schedule): void
    {
        if ((string) $schedule->type !== 'leave') {
            return;
        }

        $owner = $schedule->relationLoaded('user') ? $schedule->user : $schedule->user()->first();
        if (!$owner) {
            return;
        }

        $leaveType = $this->leaveTypeForSchedule($schedule);
        $days = $this->requestedDays($schedule);
        $year = $this->scheduleStartYearForOwner($schedule, $owner);
        $quota = $this->ensureQuota($owner, $year);

        if ($leaveType === 'sick') {
            $quota->sick_used = (float) $quota->sick_used + $days;
        } else {
            $quota->annual_used = (float) $quota->annual_used + $days;
        }

        $quota->save();
        $schedule->forceFill([
            'leave_type' => $leaveType,
            'leave_days' => $days,
        ])->save();
    }

    public function rollbackApproval(WorkSchedule $schedule): void
    {
        if ((string) $schedule->type !== 'leave') {
            return;
        }

        $owner = $schedule->relationLoaded('user') ? $schedule->user : $schedule->user()->first();
        if (!$owner) {
            return;
        }

        $leaveType = $this->leaveTypeForSchedule($schedule);
        $days = $this->requestedDays($schedule);
        $year = $this->scheduleStartYearForOwner($schedule, $owner);
        $quota = $this->ensureQuota($owner, $year);

        if ($leaveType === 'sick') {
            $quota->sick_used = max(0.0, (float) $quota->sick_used - $days);
        } else {
            $quota->annual_used = max(0.0, (float) $quota->annual_used - $days);
        }

        $quota->save();
    }

    public function requestedDays(WorkSchedule $schedule): float
    {
        if (is_numeric($schedule->leave_days) && (float) $schedule->leave_days > 0) {
            return round((float) $schedule->leave_days, 2);
        }

        $owner = $schedule->relationLoaded('user') ? $schedule->user : $schedule->user()->first();
        $timezone = $owner ? $this->timezoneFor($owner) : 'Asia/Tokyo';

        $startUtc = $this->timestampFromSchedule($schedule, 'start_at');
        $endUtc = $this->timestampFromSchedule($schedule, 'end_at');
        if (!$startUtc || !$endUtc) {
            return 1.0;
        }

        $localStart = $startUtc->copy()->timezone($timezone);
        $localEnd = $endUtc->copy()->timezone($timezone);

        if ((bool) $schedule->all_day) {
            return (float) ($localStart->startOfDay()->diffInDays($localEnd->startOfDay()) + 1);
        }

        $minutes = max(1, $localStart->diffInMinutes($localEnd));

        return max(0.5, round($minutes / 480, 2));
    }

    public function leaveTypeForSchedule(WorkSchedule $schedule): string
    {
        return (string) $schedule->leave_type === 'sick' ? 'sick' : 'annual';
    }

    private function scheduleStartYearForOwner(WorkSchedule $schedule, User $owner): int
    {
        $startUtc = $this->timestampFromSchedule($schedule, 'start_at') ?? Carbon::now('UTC');

        return (int) $startUtc->copy()->timezone($this->timezoneFor($owner))->year;
    }

    private function timezoneFor(User $user): string
    {
        return (string) data_get($user->preferences, 'workspace.timezone', 'Asia/Tokyo');
    }

    private function timestampFromSchedule(WorkSchedule $schedule, string $column): ?Carbon
    {
        $raw = $schedule->getRawOriginal($column);
        if (is_string($raw) && trim($raw) !== '') {
            try {
                return Carbon::parse($raw, 'UTC');
            } catch (\Throwable) {
                // fallback below
            }
        }

        $value = $schedule->{$column};
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return null;
    }
}
