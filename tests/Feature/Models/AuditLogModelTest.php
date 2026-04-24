<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Schema parity: AuditLog model (Plan 01 / Task 11, pulled from Task 12).
 */

it('mass-assigns all fillable fields', function () {
    $user = User::factory()->create();

    $log = AuditLog::create([
        'user_id'        => $user->id,
        'action'         => 'created',
        'auditable_type' => WorkSchedule::class,
        'auditable_id'   => 123,
        'old_values'     => ['status' => 'pending'],
        'new_values'     => ['status' => 'approved'],
        'ip_address'     => '127.0.0.1',
        'user_agent'     => 'PestTest/1.0',
    ]);

    $fresh = $log->fresh();
    expect($fresh->user_id)->toBe($user->id)
        ->and($fresh->action)->toBe('created')
        ->and($fresh->auditable_type)->toBe(WorkSchedule::class)
        ->and($fresh->auditable_id)->toBe(123)
        ->and($fresh->ip_address)->toBe('127.0.0.1')
        ->and($fresh->user_agent)->toBe('PestTest/1.0');
});

it('casts old_values and new_values to array on read', function () {
    $user = User::factory()->create();

    $log = AuditLog::create([
        'user_id'        => $user->id,
        'action'         => 'updated',
        'auditable_type' => WorkSchedule::class,
        'auditable_id'   => 1,
        'old_values'     => ['status' => 'pending'],
        'new_values'     => ['status' => 'approved'],
    ]);

    $fresh = $log->fresh();
    expect($fresh->old_values)->toBeArray()
        ->and($fresh->old_values)->toBe(['status' => 'pending'])
        ->and($fresh->new_values)->toBeArray()
        ->and($fresh->new_values)->toBe(['status' => 'approved']);
});

it('resolves auditable() polymorphic target', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $ws = WorkSchedule::factory()->create([
        'user_id' => $actor->id,
    ]);

    $log = AuditLog::where('auditable_type', WorkSchedule::class)
        ->where('auditable_id', $ws->id)
        ->first();

    expect($log)->not->toBeNull();

    $target = $log->auditable;
    expect($target)->toBeInstanceOf(WorkSchedule::class)
        ->and($target->id)->toBe($ws->id);
});

it('scopeForModel filters by auditable_type', function () {
    $user = User::factory()->create();

    AuditLog::create([
        'user_id'        => $user->id,
        'action'         => 'created',
        'auditable_type' => WorkSchedule::class,
        'auditable_id'   => 1,
    ]);
    AuditLog::create([
        'user_id'        => $user->id,
        'action'         => 'created',
        'auditable_type' => User::class,
        'auditable_id'   => $user->id,
    ]);

    $results = AuditLog::forModel(WorkSchedule::class)->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->auditable_type)->toBe(WorkSchedule::class);
});

it('scopeAction filters by action', function () {
    $user = User::factory()->create();

    AuditLog::create([
        'user_id'        => $user->id,
        'action'         => 'created',
        'auditable_type' => WorkSchedule::class,
        'auditable_id'   => 1,
    ]);
    AuditLog::create([
        'user_id'        => $user->id,
        'action'         => 'updated',
        'auditable_type' => WorkSchedule::class,
        'auditable_id'   => 1,
    ]);
    AuditLog::create([
        'user_id'        => $user->id,
        'action'         => 'deleted',
        'auditable_type' => WorkSchedule::class,
        'auditable_id'   => 1,
    ]);

    $updates = AuditLog::action('updated')->get();
    expect($updates)->toHaveCount(1)
        ->and($updates->first()->action)->toBe('updated');
});
