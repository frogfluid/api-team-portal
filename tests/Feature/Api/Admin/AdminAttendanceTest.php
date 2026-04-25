<?php

use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('admin lists attendance records, paginated, with user filter', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $target = User::factory()->create();
    AttendanceRecord::factory()
        ->for($target)
        ->count(5)
        ->sequence(
            ['date' => '2026-04-01'],
            ['date' => '2026-04-02'],
            ['date' => '2026-04-03'],
            ['date' => '2026-04-04'],
            ['date' => '2026-04-05'],
        )
        ->create();
    AttendanceRecord::factory()
        ->count(3)
        ->sequence(
            ['date' => '2026-04-10'],
            ['date' => '2026-04-11'],
            ['date' => '2026-04-12'],
        )
        ->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/attendance?user_id={$target->id}");

    $response->assertOk();
    $list = $response->json('data.records')
        ?? $response->json('records')
        ?? $response->json('data')
        ?? $response->json();
    expect($list)->toHaveCount(5);
});

it('admin lists attendance with date-range filter', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    AttendanceRecord::factory()->create(['date' => '2026-04-01']);
    AttendanceRecord::factory()->create(['date' => '2026-04-25']);
    AttendanceRecord::factory()->create(['date' => '2026-05-10']);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/attendance?from=2026-04-01&to=2026-04-30');

    $response->assertOk();
    $list = $response->json('data.records')
        ?? $response->json('records')
        ?? $response->json('data')
        ?? $response->json();
    expect(count($list))->toBe(2);
});

it('non-admin gets 403', function () {
    $u = User::factory()->create();
    $response = $this->actingAs($u, 'sanctum')->getJson('/api/admin/attendance');
    $response->assertStatus(403);
});
