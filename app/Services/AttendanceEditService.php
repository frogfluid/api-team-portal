<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\SystemMessageNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AttendanceEditService
{
    public function __construct(
        private readonly AttendanceDailyLogSyncer $syncer,
    ) {
    }

    public function create(User $actor, array $data): AttendanceRecord
    {
        return DB::transaction(function () use ($actor, $data) {
            $record = new AttendanceRecord([
                'user_id' => $data['user_id'],
                'date' => $data['date'],
                'clock_in_at' => $data['clock_in_at'] ?? null,
                'clock_out_at' => $data['clock_out_at'] ?? null,
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'is_manual_override' => true,
            ]);

            $record->work_duration_minutes = $this->computeDuration($record);
            $record->save();

            $this->runSideEffects($actor, $record, null, $data);

            return $record;
        });
    }

    /**
     * Compute the projected attendance record (work_duration_minutes etc.) without persisting.
     *
     * Implementation note: we wrap a normal create() call inside a transaction that we always
     * roll back. This guarantees byte-identical computation with create() (Plan 01 parity rule)
     * without refactoring its internals.
     */
    public function preview(array $data): array
    {
        $actor = User::query()->find($data['user_id'])
            ?? new User(['id' => $data['user_id']]);

        DB::beginTransaction();
        try {
            $record = $this->create($actor, $data);
            $array = $record->toArray();
            DB::rollBack();
            return $array;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(User $actor, AttendanceRecord $record, array $data): AttendanceRecord
    {
        return DB::transaction(function () use ($actor, $record, $data) {
            $before = $record->getOriginal();

            $record->clock_in_at = $data['clock_in_at'] ?? null;
            $record->clock_out_at = $data['clock_out_at'] ?? null;
            $record->status = $data['status'];
            $record->note = $data['note'] ?? null;
            $record->is_manual_override = true;
            $record->work_duration_minutes = $this->computeDuration($record);
            $record->save();

            $this->runSideEffects($actor, $record, $before, $data);

            return $record;
        });
    }

    private function computeDuration(AttendanceRecord $record): int
    {
        if (!$record->clock_in_at || !$record->clock_out_at) {
            return 0;
        }
        $in = $record->clock_in_at instanceof Carbon ? $record->clock_in_at : Carbon::parse($record->clock_in_at);
        $out = $record->clock_out_at instanceof Carbon ? $record->clock_out_at : Carbon::parse($record->clock_out_at);
        return max(1, min(1440, (int) ceil($in->floatDiffInMinutes($out))));
    }

    private function runSideEffects(User $actor, AttendanceRecord $record, ?array $before, array $data): void
    {
        // 1. Daily log sync
        $this->syncer->sync($record->fresh());

        // 2. Audit log
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $before === null ? 'attendance.create' : 'attendance.update',
            'auditable_type' => AttendanceRecord::class,
            'auditable_id' => $record->id,
            'old_values' => $before,
            'new_values' => array_merge($record->getAttributes(), [
                'reason' => $data['note'],
                'post_payroll' => (bool) ($data['post_payroll'] ?? false),
            ]),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        // 3. Notify employee — SystemMessageNotification takes ($title, $message, $link, $meta) positionally
        $dateStr = Carbon::parse($record->date)->toDateString();
        Notification::sendNow(
            $record->user,
            new SystemMessageNotification(
                __('Your attendance record was updated'),
                __('An administrator adjusted your attendance for :date. Please review at /app/attendance.', ['date' => $dateStr]),
                '/app/attendance?month='.Carbon::parse($record->date)->format('Y-m'),
                ['is_banner' => false, 'actor_id' => $actor->id],
            ),
            ['database'] // Restrict to in-app only — spec §5.5 "No email — in-app only"
        );
    }
}
