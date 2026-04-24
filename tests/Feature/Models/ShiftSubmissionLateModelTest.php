<?php

use App\Models\ShiftSubmissionLate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Schema parity: ShiftSubmissionLate model (Plan 01 / Task 12).
 */

it('mass-assigns all fillable fields', function () {
    $user = User::factory()->create();

    $late = ShiftSubmissionLate::create([
        'user_id'    => $user->id,
        'iso_year'   => 2026,
        'iso_week'   => 17,
        'flagged_at' => '2026-04-24 10:00:00',
    ]);

    $fresh = $late->fresh();
    expect($fresh->user_id)->toBe($user->id)
        ->and($fresh->iso_year)->toBe(2026)
        ->and($fresh->iso_week)->toBe(17);
});

it('casts flagged_at to Carbon datetime', function () {
    $late = ShiftSubmissionLate::factory()->create([
        'flagged_at' => '2026-04-24 10:00:00',
    ]);

    expect($late->fresh()->flagged_at)->toBeInstanceOf(Carbon::class);
});

it('user() relation resolves to the subject user', function () {
    $user = User::factory()->create();
    $late = ShiftSubmissionLate::factory()->create(['user_id' => $user->id]);

    expect($late->user)->toBeInstanceOf(User::class)
        ->and($late->user->id)->toBe($user->id);
});

it('scopeForUser filters by user_id', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $mine = ShiftSubmissionLate::factory()->create(['user_id' => $u1->id, 'iso_year' => 2026, 'iso_week' => 10]);
    ShiftSubmissionLate::factory()->create(['user_id' => $u2->id, 'iso_year' => 2026, 'iso_week' => 11]);

    $results = ShiftSubmissionLate::forUser($u1->id)->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($mine->id);
});

it('scopeInMonth filters by flagged_at between start and end of month (inclusive boundaries)', function () {
    $user = User::factory()->create();

    // First instant of April 2026
    $startBoundary = ShiftSubmissionLate::factory()->create([
        'user_id'    => $user->id,
        'iso_year'   => 2026,
        'iso_week'   => 14,
        'flagged_at' => '2026-04-01 00:00:00',
    ]);
    // Mid-month
    $mid = ShiftSubmissionLate::factory()->create([
        'user_id'    => $user->id,
        'iso_year'   => 2026,
        'iso_week'   => 16,
        'flagged_at' => '2026-04-15 09:00:00',
    ]);
    // End of month
    $endBoundary = ShiftSubmissionLate::factory()->create([
        'user_id'    => $user->id,
        'iso_year'   => 2026,
        'iso_week'   => 18,
        'flagged_at' => '2026-04-30 23:59:59',
    ]);
    // Outside: March 31
    $before = ShiftSubmissionLate::factory()->create([
        'user_id'    => $user->id,
        'iso_year'   => 2026,
        'iso_week'   => 13,
        'flagged_at' => '2026-03-31 23:59:59',
    ]);
    // Outside: May 1
    $after = ShiftSubmissionLate::factory()->create([
        'user_id'    => $user->id,
        'iso_year'   => 2026,
        'iso_week'   => 19,
        'flagged_at' => '2026-05-01 00:00:01',
    ]);

    $results = ShiftSubmissionLate::inMonth(2026, 4)->pluck('id')->all();
    expect($results)->toContain($startBoundary->id, $mid->id, $endBoundary->id)
        ->not->toContain($before->id)
        ->not->toContain($after->id);
});
