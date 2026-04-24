<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-time migrator for legacy work_schedules.type='remote_request' rows.
 *
 * Copies each legacy row into the new remote_work_requests table (defaulting
 * region='domestic'; admin can re-classify manually) and tags the source row
 * as '_deprecated_remote_request' so it stays on disk for rollback.
 *
 * Extracted into a service so the feature test can exercise the logic without
 * invoking `artisan migrate` (Nova blocks the local autoload in this project).
 */
class LegacyRemoteRequestMigrator
{
    public const LEGACY_TYPE = 'remote_request';
    public const DEPRECATED_TYPE = '_deprecated_remote_request';
    public const LEGACY_DELIVERABLES_MARKER = '(legacy — pending update)';
    public const LEGACY_ENVIRONMENT_MARKER = '(legacy — pending update)';

    /**
     * Run the migration. Returns the number of legacy rows processed.
     *
     * Idempotent: after the first run the source rows carry the deprecated tag,
     * so a second invocation finds nothing to copy and returns 0.
     */
    public function migrate(): int
    {
        $now = now();
        $count = 0;

        DB::table('work_schedules')
            ->where('type', self::LEGACY_TYPE)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now, &$count) {
                $insert = [];
                foreach ($rows as $row) {
                    $insert[] = [
                        'user_id'          => $row->user_id,
                        'region'           => 'domestic',
                        'start_date'       => Carbon::parse($row->start_at)->toDateString(),
                        'end_date'         => Carbon::parse($row->end_at)->toDateString(),
                        'reason'           => $row->note !== null && $row->note !== ''
                            ? $row->note
                            : '(migrated from legacy)',
                        'deliverables'     => self::LEGACY_DELIVERABLES_MARKER,
                        'work_environment' => self::LEGACY_ENVIRONMENT_MARKER,
                        'status'           => $row->status ?? 'pending',
                        'approved_by'      => $row->approved_by,
                        'approved_at'      => $row->approved_at,
                        'rejection_reason' => null,
                        'created_at'       => $row->created_at ?? $now,
                        'updated_at'       => $now,
                    ];
                }
                if (!empty($insert)) {
                    DB::table('remote_work_requests')->insert($insert);
                    $count += count($insert);
                }
            });

        // Tag legacy rows instead of deleting — keeps archive for rollback (D11).
        DB::table('work_schedules')
            ->where('type', self::LEGACY_TYPE)
            ->update(['type' => self::DEPRECATED_TYPE]);

        return $count;
    }

    /**
     * Reverse the tag change and delete inserted rows (best-effort rollback).
     */
    public function rollback(): void
    {
        DB::table('work_schedules')
            ->where('type', self::DEPRECATED_TYPE)
            ->update(['type' => self::LEGACY_TYPE]);

        DB::table('remote_work_requests')
            ->where('deliverables', self::LEGACY_DELIVERABLES_MARKER)
            ->delete();
    }
}
