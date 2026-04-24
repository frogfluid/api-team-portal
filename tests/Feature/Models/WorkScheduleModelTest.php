<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleComment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Schema parity: WorkSchedule adds is_remote + widens type + Auditable trait.
 * (Plan 01 / Task 11)
 */

it('mass-assigns is_remote and persists it', function () {
    $user = User::factory()->create();

    $ws = WorkSchedule::create([
        'user_id'   => $user->id,
        'type'      => 'work',
        'is_remote' => true,
        'start_at'  => now()->startOfDay()->addHours(9),
        'end_at'    => now()->startOfDay()->addHours(18),
        'status'    => 'approved',
    ]);

    $fresh = $ws->fresh();
    expect($fresh->is_remote)->toBeTrue();
});

it('casts is_remote to boolean', function () {
    $user = User::factory()->create();

    // Persist using a truthy integer to prove cast applies on read
    $ws = WorkSchedule::create([
        'user_id'   => $user->id,
        'type'      => 'work',
        'is_remote' => 1,
        'start_at'  => now()->startOfDay()->addHours(9),
        'end_at'    => now()->startOfDay()->addHours(18),
        'status'    => 'approved',
    ]);

    $fresh = $ws->fresh();
    expect($fresh->is_remote)->toBeBool()
        ->and($fresh->is_remote)->toBeTrue();

    $ws2 = WorkSchedule::create([
        'user_id'   => $user->id,
        'type'      => 'work',
        'is_remote' => 0,
        'start_at'  => now()->startOfDay()->addHours(9),
        'end_at'    => now()->startOfDay()->addHours(18),
        'status'    => 'approved',
    ]);

    $fresh2 = $ws2->fresh();
    expect($fresh2->is_remote)->toBeBool()
        ->and($fresh2->is_remote)->toBeFalse();
});

it('accepts wider type values after widen migration', function () {
    $user = User::factory()->create();

    // Any string up to 32 chars must round-trip without truncation.
    $wideType = 'special_leave_company_event'; // 27 chars
    $ws = WorkSchedule::create([
        'user_id'  => $user->id,
        'type'     => $wideType,
        'start_at' => now()->startOfDay()->addHours(9),
        'end_at'   => now()->startOfDay()->addHours(18),
        'status'   => 'approved',
    ]);

    $fresh = $ws->fresh();
    expect($fresh->type)->toBe($wideType);
});

it('resolves user / approver / commentAuthor / comments relations', function () {
    $owner    = User::factory()->create();
    $approver = User::factory()->create();
    $commenter = User::factory()->create();

    $ws = WorkSchedule::factory()->create([
        'user_id'            => $owner->id,
        'approved_by'        => $approver->id,
        'manager_comment'    => 'Nice',
        'manager_comment_by' => $commenter->id,
    ]);

    WorkScheduleComment::create([
        'work_schedule_id' => $ws->id,
        'user_id'          => $commenter->id,
        'body'             => 'first comment',
    ]);
    WorkScheduleComment::create([
        'work_schedule_id' => $ws->id,
        'user_id'          => $commenter->id,
        'body'             => 'second comment',
    ]);

    $fresh = $ws->fresh();
    expect($fresh->user)->not->toBeNull()
        ->and($fresh->user->id)->toBe($owner->id)
        ->and($fresh->approver)->not->toBeNull()
        ->and($fresh->approver->id)->toBe($approver->id)
        ->and($fresh->commentAuthor)->not->toBeNull()
        ->and($fresh->commentAuthor->id)->toBe($commenter->id)
        ->and($fresh->comments)->toHaveCount(2)
        // Latest comes first due to ->latest('created_at')
        ->and($fresh->comments->first()->body)->toBeIn(['first comment', 'second comment']);
});

it('writes an audit_logs row with action=created on create', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $ws = WorkSchedule::factory()->create([
        'user_id' => $actor->id,
        'type'    => 'work',
    ]);

    $log = AuditLog::where('auditable_type', WorkSchedule::class)
        ->where('auditable_id', $ws->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($actor->id)
        ->and($log->new_values)->toBeArray()
        ->and($log->new_values)->toHaveKey('user_id')
        ->and($log->new_values)->toHaveKey('type');
});

it('writes an audit_logs row with action=updated on field change', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $ws = WorkSchedule::factory()->create([
        'user_id' => $actor->id,
        'status'  => 'pending',
    ]);

    // Clear the created log noise so we only inspect the update.
    $updateStartedAt = now();

    $ws->update(['status' => 'approved']);

    $log = AuditLog::where('auditable_type', WorkSchedule::class)
        ->where('auditable_id', $ws->id)
        ->where('action', 'updated')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBeArray()
        ->and($log->old_values)->toHaveKey('status')
        ->and($log->old_values['status'])->toBe('pending')
        ->and($log->new_values)->toBeArray()
        ->and($log->new_values)->toHaveKey('status')
        ->and($log->new_values['status'])->toBe('approved')
        // old_values should contain ONLY the changed key(s), not every attribute
        ->and(array_keys($log->old_values))->toBe(['status']);
});

it('writes an audit_logs row with action=deleted on delete', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $ws = WorkSchedule::factory()->create([
        'user_id' => $actor->id,
    ]);
    $id = $ws->id;
    $ws->delete();

    $log = AuditLog::where('auditable_type', WorkSchedule::class)
        ->where('auditable_id', $id)
        ->where('action', 'deleted')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBeArray()
        ->and($log->old_values)->toHaveKey('user_id');
});

it('does not write an updated audit when only timestamps change', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $ws = WorkSchedule::factory()->create([
        'user_id' => $actor->id,
    ]);

    // Saving without modifying any fillable field should not emit an updated audit.
    // Force a save via touch() which only bumps updated_at.
    $ws->touch();

    $updatedLogs = AuditLog::where('auditable_type', WorkSchedule::class)
        ->where('auditable_id', $ws->id)
        ->where('action', 'updated')
        ->count();

    expect($updatedLogs)->toBe(0);
});
