<?php

use App\Enums\UserRole;
use App\Models\JobScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists scopes assigned to the authenticated user', function () {
    $u = User::factory()->create();
    $assigned = JobScope::factory()->count(2)->create();
    foreach ($assigned as $scope) {
        $scope->users()->attach($u->id);
    }
    JobScope::factory()->count(3)->create(); // unassigned, should not appear

    $response = $this->actingAs($u, 'sanctum')->getJson('/api/job-scopes');
    $response->assertOk();
    $list = $response->json('data.job_scopes')
        ?? $response->json('job_scopes')
        ?? $response->json('data')
        ?? $response->json();
    expect($list)->toHaveCount(2);
});

it('forbids viewing an unassigned scope (403)', function () {
    $u = User::factory()->create();
    $scope = JobScope::factory()->create();

    $response = $this->actingAs($u, 'sanctum')->getJson("/api/job-scopes/{$scope->id}");
    $response->assertStatus(403);
});

it('admin sees any scope via user-side show', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $scope = JobScope::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')->getJson("/api/job-scopes/{$scope->id}");
    $response->assertOk();
});
