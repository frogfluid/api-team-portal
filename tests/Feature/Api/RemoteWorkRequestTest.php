<?php

use App\Enums\UserRole;
use App\Models\RemoteWorkRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists own requests for a regular user', function () {
    $u = User::factory()->create();
    RemoteWorkRequest::factory()->for($u)->count(2)->create();
    RemoteWorkRequest::factory()->create(); // someone else's

    $response = $this->actingAs($u, 'sanctum')->getJson('/api/remote-work-requests');
    $response->assertOk();
    $list = $response->json('data.requests')
        ?? $response->json('requests')
        ?? $response->json('data')
        ?? $response->json();
    expect($list)->toHaveCount(2);
});

it('lists all requests for a reviewer', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    RemoteWorkRequest::factory()->count(3)->create();

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/remote-work-requests');
    $response->assertOk();
    $list = $response->json('data.requests')
        ?? $response->json('requests')
        ?? $response->json('data')
        ?? $response->json();
    expect($list)->toHaveCount(3);
});

it('creates a request with valid input', function () {
    $u = User::factory()->create();
    $response = $this->actingAs($u, 'sanctum')->postJson('/api/remote-work-requests', [
        'region' => 'domestic',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-03',
        'reason' => 'visit family',
        'deliverables' => 'finish weekly report',
        'work_environment' => 'home wifi',
    ]);
    $response->assertCreated();
    expect(RemoteWorkRequest::where('user_id', $u->id)->count())->toBe(1);
});

it('rejects creation with missing fields (422 + VALIDATION_ERROR)', function () {
    $u = User::factory()->create();
    $response = $this->actingAs($u, 'sanctum')->postJson('/api/remote-work-requests', []);
    $response->assertStatus(422);
    expect($response->json('error_code'))->toBe('VALIDATION_ERROR');
});

it('forbids approve from a non-reviewer (403 + FORBIDDEN)', function () {
    $u = User::factory()->create();
    $req = RemoteWorkRequest::factory()->create(['status' => 'pending']);
    $response = $this->actingAs($u, 'sanctum')->postJson("/api/remote-work-requests/{$req->id}/approve");
    $response->assertStatus(403);
    expect($response->json('error_code'))->toBe('FORBIDDEN');
});

it('approves a pending request as a reviewer', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $req = RemoteWorkRequest::factory()->create(['status' => 'pending']);

    $response = $this->actingAs($admin, 'sanctum')->postJson("/api/remote-work-requests/{$req->id}/approve");
    $response->assertOk();
    expect($req->fresh()->status)->toBe('approved');
    expect($req->fresh()->approved_by)->toBe($admin->id);
});

it('rejects with reason as a reviewer', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $req = RemoteWorkRequest::factory()->create(['status' => 'pending']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/remote-work-requests/{$req->id}/reject", ['rejection_reason' => 'no']);
    $response->assertOk();
    expect($req->fresh()->status)->toBe('rejected');
    expect($req->fresh()->rejection_reason)->toBe('no');
});
