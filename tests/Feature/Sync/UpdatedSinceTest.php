<?php

use App\Models\User;
use App\Models\WeeklyReport;
use App\Models\WorkDailyLog;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Insert a notification row directly with controlled timestamps.
 * Returns the persisted DatabaseNotification.
 */
function makeNotification(User $user, Carbon $updatedAt): DatabaseNotification
{
    $id = (string) Str::uuid();
    DatabaseNotification::query()->insert([
        'id' => $id,
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['type' => 'system', 'title' => 'T', 'message' => 'M']),
        'read_at' => null,
        'created_at' => $updatedAt,
        'updated_at' => $updatedAt,
    ]);

    return DatabaseNotification::query()->findOrFail($id);
}

it('filters notifications by updated_since', function () {
    $user = User::factory()->create();
    $old = makeNotification($user, Carbon::parse('2026-01-01 00:00:00'));
    $new = makeNotification($user, Carbon::parse('2026-04-01 00:00:00'));

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/notifications?updated_since=2026-02-01T00:00:00Z');

    $response->assertOk();
    $ids = collect($response->json('notifications') ?? $response->json('data') ?? $response->json())
        ->pluck('id');
    expect($ids)->toContain(crc32($new->id));
    expect($ids)->not->toContain(crc32($old->id));
});

it('returns the full set when force_full=true is passed alongside updated_since', function () {
    $user = User::factory()->create();
    $old = makeNotification($user, Carbon::parse('2026-01-01 00:00:00'));
    $new = makeNotification($user, Carbon::parse('2026-04-01 00:00:00'));

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/notifications?updated_since=2026-02-01T00:00:00Z&force_full=true');

    $response->assertOk();
    $ids = collect($response->json('notifications') ?? $response->json('data') ?? $response->json())
        ->pluck('id');
    expect($ids)->toContain(crc32($new->id));
    expect($ids)->toContain(crc32($old->id));
});

it('falls through to full list on malformed updated_since (no error)', function () {
    $user = User::factory()->create();
    makeNotification($user, Carbon::parse('2026-01-01 00:00:00'));

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/notifications?updated_since=not-a-date');

    $response->assertOk();
});

it('filters daily logs by updated_since', function () {
    $user = User::factory()->create();
    $old = WorkDailyLog::factory()->for($user)->create();
    \DB::table('work_daily_logs')->where('id', $old->id)->update(['updated_at' => '2026-01-01 00:00:00']);
    $new = WorkDailyLog::factory()->for($user)->create();
    \DB::table('work_daily_logs')->where('id', $new->id)->update(['updated_at' => '2026-04-01 00:00:00']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/daily-logs?updated_since=2026-02-01T00:00:00Z');

    $response->assertOk();
    $ids = collect($response->json('logs') ?? $response->json('daily_logs') ?? $response->json('data') ?? $response->json())
        ->pluck('id');
    expect($ids)->toContain($new->id);
    expect($ids)->not->toContain($old->id);
});

it('filters weekly reports by updated_since', function () {
    $user = User::factory()->create();
    $old = WeeklyReport::factory()->for($user)->create([
        'week_start_date' => Carbon::parse('2025-12-29')->toDateString(),
    ]);
    \DB::table('weekly_reports')->where('id', $old->id)->update(['updated_at' => '2026-01-01 00:00:00']);
    $new = WeeklyReport::factory()->for($user)->create([
        'week_start_date' => Carbon::parse('2026-03-30')->toDateString(),
    ]);
    \DB::table('weekly_reports')->where('id', $new->id)->update(['updated_at' => '2026-04-01 00:00:00']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/weekly-reports?updated_since=2026-02-01T00:00:00Z');

    $response->assertOk();
    $ids = collect($response->json('reports') ?? $response->json('weekly_reports') ?? $response->json('data') ?? $response->json())
        ->pluck('id');
    expect($ids)->toContain($new->id);
    expect($ids)->not->toContain($old->id);
});

it('filters work schedules by updated_since', function () {
    $user = User::factory()->create();
    $old = WorkSchedule::factory()->for($user)->create([
        'start_at' => Carbon::parse('2026-01-01 09:00:00'),
        'end_at' => Carbon::parse('2026-01-01 18:00:00'),
    ]);
    \DB::table('work_schedules')->where('id', $old->id)->update(['updated_at' => '2026-01-01 00:00:00']);
    $new = WorkSchedule::factory()->for($user)->create([
        'start_at' => Carbon::parse('2026-04-01 09:00:00'),
        'end_at' => Carbon::parse('2026-04-01 18:00:00'),
    ]);
    \DB::table('work_schedules')->where('id', $new->id)->update(['updated_at' => '2026-04-01 00:00:00']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/work-schedules?updated_since=2026-02-01T00:00:00Z');

    $response->assertOk();
    $ids = collect($response->json('schedules') ?? $response->json('work_schedules') ?? $response->json('data') ?? $response->json())
        ->pluck('id');
    expect($ids)->toContain($new->id);
    expect($ids)->not->toContain($old->id);
});

it('filters leave requests by updated_since', function () {
    // Note: leaves are stored as WorkSchedule rows where type='leave'
    // (no separate LeaveRequest model exists in this codebase).
    $user = User::factory()->create();
    $old = WorkSchedule::factory()->for($user)->create([
        'type' => 'leave',
        'leave_type' => 'annual',
        'all_day' => true,
        'leave_days' => 1,
        'start_at' => Carbon::parse('2026-01-01 00:00:00'),
        'end_at' => Carbon::parse('2026-01-01 23:59:59'),
        'status' => 'pending',
    ]);
    \DB::table('work_schedules')->where('id', $old->id)->update(['updated_at' => '2026-01-01 00:00:00']);
    $new = WorkSchedule::factory()->for($user)->create([
        'type' => 'leave',
        'leave_type' => 'annual',
        'all_day' => true,
        'leave_days' => 1,
        'start_at' => Carbon::parse('2026-04-01 00:00:00'),
        'end_at' => Carbon::parse('2026-04-01 23:59:59'),
        'status' => 'pending',
    ]);
    \DB::table('work_schedules')->where('id', $new->id)->update(['updated_at' => '2026-04-01 00:00:00']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/leaves?updated_since=2026-02-01T00:00:00Z');

    $response->assertOk();
    $ids = collect($response->json('leaves') ?? $response->json('leave_requests') ?? $response->json('data') ?? $response->json())
        ->pluck('id');
    expect($ids)->toContain($new->id);
    expect($ids)->not->toContain($old->id);
});
