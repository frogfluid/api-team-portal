<?php

use App\Enums\UserRole;
use App\Models\CleaningDuty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns null when no duty for today', function () {
    $u = User::factory()->create();
    $response = $this->actingAs($u, 'sanctum')->getJson('/api/cleaning-duty/today');
    $response->assertOk();
    expect($response->json('data.duty') ?? $response->json('duty'))->toBeNull();
});

it('returns todays duty with assigned users', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    CleaningDuty::create([
        'date' => Carbon::today()->toDateString(),
        'assigned_user_ids' => [$u1->id, $u2->id],
        'assigned_by' => $admin->id,
    ]);

    $response = $this->actingAs($u1, 'sanctum')->getJson('/api/cleaning-duty/today');
    $response->assertOk();
    $duty = $response->json('data.duty') ?? $response->json('duty');
    expect($duty)->not->toBeNull();
    expect($duty['assigned_user_ids'])->toContain($u1->id, $u2->id);
    expect($duty['assigned_users'])->toHaveCount(2);
});

it('non-admin cannot list history (403)', function () {
    $u = User::factory()->create();
    $response = $this->actingAs($u, 'sanctum')->getJson('/api/cleaning-duty');
    $response->assertStatus(403);
    expect($response->json('error_code'))->toBe('FORBIDDEN');
});

it('admin lists history filtered by date', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    CleaningDuty::create(['date' => '2026-04-01', 'assigned_user_ids' => [1], 'assigned_by' => $admin->id]);
    CleaningDuty::create(['date' => '2026-04-15', 'assigned_user_ids' => [1], 'assigned_by' => $admin->id]);
    CleaningDuty::create(['date' => '2026-05-01', 'assigned_user_ids' => [1], 'assigned_by' => $admin->id]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/cleaning-duty?from=2026-04-01&to=2026-04-30');
    $response->assertOk();
    $list = $response->json('data.duties') ?? $response->json('duties');
    expect($list)->toHaveCount(2);
});

it('admin assigns todays duty', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/cleaning-duty', [
        'user_ids' => [$u1->id, $u2->id],
    ]);
    $response->assertCreated();

    $today = Carbon::today()->toDateString();
    $duty = CleaningDuty::where('date', $today)->first();
    expect($duty)->not->toBeNull();
    expect($duty->assigned_user_ids)->toEqualCanonicalizing([$u1->id, $u2->id]);
});

it('non-admin cannot assign (403)', function () {
    $u = User::factory()->create();
    $other = User::factory()->create();
    $response = $this->actingAs($u, 'sanctum')->postJson('/api/cleaning-duty', ['user_ids' => [$other->id]]);
    $response->assertStatus(403);
});

it('rejects empty user_ids with 422', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/cleaning-duty', ['user_ids' => []]);
    $response->assertStatus(422);
});
