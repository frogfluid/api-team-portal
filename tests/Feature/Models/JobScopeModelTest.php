<?php

use App\Models\JobScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Schema parity: JobScope model (Plan 01 / Task 12).
 */

it('mass-assigns all fillable fields', function () {
    $scope = JobScope::create([
        'name'                => 'Translation',
        'description'         => 'JP <-> EN translation work',
        'has_external_output' => true,
    ]);

    $fresh = $scope->fresh();
    expect($fresh->name)->toBe('Translation')
        ->and($fresh->description)->toBe('JP <-> EN translation work')
        ->and($fresh->has_external_output)->toBeTrue();
});

it('casts has_external_output to boolean', function () {
    $scope = JobScope::factory()->create(['has_external_output' => 0]);
    expect($scope->fresh()->has_external_output)->toBeFalse();

    $scope2 = JobScope::factory()->create(['has_external_output' => 1]);
    expect($scope2->fresh()->has_external_output)->toBeTrue();
});

it('users() belongsToMany relation attaches and retrieves users via job_scope_user pivot', function () {
    $scope = JobScope::factory()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $scope->users()->attach([$u1->id, $u2->id]);

    $ids = $scope->users()->pluck('users.id')->sort()->values()->all();
    expect($ids)->toBe(collect([$u1->id, $u2->id])->sort()->values()->all());
});
