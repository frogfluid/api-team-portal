<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('non-admin cannot view audit logs (403)', function () {
    $u = User::factory()->create();
    $response = $this->actingAs($u, 'sanctum')->getJson('/api/admin/audit-logs');
    $response->assertStatus(403);
    expect($response->json('error_code'))->toBe('FORBIDDEN');
});

it('admin lists audit logs paginated, newest first', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $other = User::factory()->create();

    $earlier = AuditLog::create([
        'user_id' => $other->id, 'action' => 'created',
        'auditable_type' => 'App\\Models\\Task', 'auditable_id' => 1,
        'old_values' => null, 'new_values' => ['title' => 'New task'],
        'ip_address' => '127.0.0.1',
    ]);
    $earlier->forceFill(['created_at' => now()->subMinutes(5)])->save();

    $later = AuditLog::create([
        'user_id' => $other->id, 'action' => 'updated',
        'auditable_type' => 'App\\Models\\Task', 'auditable_id' => 1,
        'old_values' => ['title' => 'New task'], 'new_values' => ['title' => 'Renamed'],
        'ip_address' => '127.0.0.1',
    ]);
    $later->forceFill(['created_at' => now()])->save();

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/audit-logs');
    $response->assertOk();
    $body = $response->json('data') ?? $response->json();
    expect($body['logs'])->toHaveCount(2);
    expect($body['logs'][0]['action'])->toBe('updated');  // newest first
});

it('admin can filter by action', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $other = User::factory()->create();
    AuditLog::create(['user_id' => $other->id, 'action' => 'created', 'auditable_type' => 'X', 'auditable_id' => 1]);
    AuditLog::create(['user_id' => $other->id, 'action' => 'deleted', 'auditable_type' => 'X', 'auditable_id' => 1]);
    AuditLog::create(['user_id' => $other->id, 'action' => 'created', 'auditable_type' => 'Y', 'auditable_id' => 2]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/audit-logs?action=created');
    $response->assertOk();
    $body = $response->json('data') ?? $response->json();
    expect($body['logs'])->toHaveCount(2);
});

it('admin lists distinct actions', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $other = User::factory()->create();
    AuditLog::create(['user_id' => $other->id, 'action' => 'created', 'auditable_type' => 'X', 'auditable_id' => 1]);
    AuditLog::create(['user_id' => $other->id, 'action' => 'updated', 'auditable_type' => 'X', 'auditable_id' => 1]);
    AuditLog::create(['user_id' => $other->id, 'action' => 'created', 'auditable_type' => 'Y', 'auditable_id' => 2]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/audit-logs/actions');
    $response->assertOk();
    $actions = $response->json('data.actions') ?? $response->json('actions');
    expect($actions)->toEqualCanonicalizing(['created', 'updated']);
});
