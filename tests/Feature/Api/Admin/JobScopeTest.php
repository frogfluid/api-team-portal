<?php

use App\Enums\UserRole;
use App\Models\JobScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('admin creates a scope', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $payload = [
        'name' => 'Sales lead pipeline',
        'description' => 'maintain pipeline freshness',
    ];
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/job-scopes', $payload);
    $response->assertCreated();
    expect(JobScope::where('name', 'Sales lead pipeline')->exists())->toBeTrue();
});

it('admin assigns users to a scope', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $scope = JobScope::factory()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    // Pre-attach a different user to ensure sync replaces existing pivot rows
    $existing = User::factory()->create();
    $scope->users()->attach($existing->id);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/job-scopes/{$scope->id}/users", ['user_ids' => [$u1->id, $u2->id]]);
    $response->assertOk();
    expect($scope->fresh()->users()->pluck('users.id')->all())->toEqualCanonicalizing([$u1->id, $u2->id]);
});

it('admin update', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $scope = JobScope::factory()->create(['name' => 'old']);
    $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/job-scopes/{$scope->id}", ['name' => 'new']);
    $response->assertOk();
    expect($scope->fresh()->name)->toBe('new');
});

it('admin delete', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $scope = JobScope::factory()->create();
    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/job-scopes/{$scope->id}");
    $response->assertOk();
    expect(JobScope::find($scope->id))->toBeNull();
});

it('non-admin gets 403 on admin endpoints', function () {
    $u = User::factory()->create();
    $r = $this->actingAs($u, 'sanctum')->postJson('/api/admin/job-scopes', ['name' => 'x']);
    $r->assertStatus(403);
});
