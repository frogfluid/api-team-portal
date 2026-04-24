<?php

use App\Models\CleaningDuty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Schema parity: CleaningDuty model (Plan 01 / Task 12).
 */

it('mass-assigns all fillable fields', function () {
    $assigner = User::factory()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $duty = CleaningDuty::create([
        'date'              => '2026-04-20',
        'assigned_user_ids' => [$u1->id, $u2->id],
        'assigned_by'       => $assigner->id,
    ]);

    $fresh = $duty->fresh();
    expect($fresh->assigned_by)->toBe($assigner->id)
        ->and($fresh->assigned_user_ids)->toBe([$u1->id, $u2->id]);
});

it('casts date and assigned_user_ids', function () {
    $assigner = User::factory()->create();
    $duty = CleaningDuty::create([
        'date'              => '2026-04-20',
        'assigned_user_ids' => [1, 2, 3],
        'assigned_by'       => $assigner->id,
    ]);

    $fresh = $duty->fresh();
    expect($fresh->date)->toBeInstanceOf(Carbon::class)
        ->and($fresh->date->toDateString())->toBe('2026-04-20')
        ->and($fresh->assigned_user_ids)->toBeArray()
        ->and($fresh->assigned_user_ids)->toBe([1, 2, 3]);
});

it('assigner() relation resolves to the creator', function () {
    $assigner = User::factory()->create();
    $duty = CleaningDuty::create([
        'date'              => '2026-04-21',
        'assigned_user_ids' => [],
        'assigned_by'       => $assigner->id,
    ]);

    expect($duty->assigner)->toBeInstanceOf(User::class)
        ->and($duty->assigner->id)->toBe($assigner->id);
});

it('assignedUsers() returns the User collection for the stored IDs', function () {
    $assigner = User::factory()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    User::factory()->create(); // not assigned

    $duty = CleaningDuty::create([
        'date'              => '2026-04-22',
        'assigned_user_ids' => [$u1->id, $u2->id],
        'assigned_by'       => $assigner->id,
    ]);

    $users = $duty->assignedUsers();
    expect($users->pluck('id')->sort()->values()->all())
        ->toBe(collect([$u1->id, $u2->id])->sort()->values()->all());
});

it('isUserAssigned returns true/false as expected', function () {
    $assigner = User::factory()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $duty = CleaningDuty::create([
        'date'              => '2026-04-23',
        'assigned_user_ids' => [$u1->id],
        'assigned_by'       => $assigner->id,
    ]);

    expect($duty->isUserAssigned($u1->id))->toBeTrue()
        ->and($duty->isUserAssigned($u2->id))->toBeFalse();
});

it('scopeForDate returns only duties on the given date', function () {
    $assigner = User::factory()->create();
    $match = CleaningDuty::create([
        'date'              => '2026-04-24',
        'assigned_user_ids' => [],
        'assigned_by'       => $assigner->id,
    ]);
    CleaningDuty::create([
        'date'              => '2026-04-25',
        'assigned_user_ids' => [],
        'assigned_by'       => $assigner->id,
    ]);

    $results = CleaningDuty::forDate('2026-04-24')->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});
