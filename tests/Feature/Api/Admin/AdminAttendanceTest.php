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

it('admin creates an attendance record via service', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $target = User::factory()->create();

    $payload = [
        'user_id' => $target->id,
        'date' => '2026-04-25',
        'clock_in_at' => '2026-04-25 09:00:00',
        'clock_out_at' => '2026-04-25 18:00:00',
        'status' => 'normal',
    ];
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/attendance', $payload);
    $response->assertCreated();
    expect(\App\Models\AttendanceRecord::where('user_id', $target->id)->where('date', '2026-04-25')->exists())->toBeTrue();
});

it('admin update changes status', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $rec = \App\Models\AttendanceRecord::factory()->create([
        'date' => '2026-04-25',
        'status' => 'normal',
    ]);
    $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/attendance/{$rec->id}", [
        'user_id' => $rec->user_id,
        'date' => '2026-04-25',
        'clock_in_at' => '2026-04-25 09:00:00',
        'clock_out_at' => '2026-04-25 18:00:00',
        'status' => 'on_leave',
    ]);
    $response->assertOk();
    expect($rec->fresh()->status)->toBe('on_leave');
});

it('returns 409 PAYROLL_LOCKED when target month payroll status is paid', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $target = User::factory()->create();

    \App\Models\Payroll::factory()->for($target)->create([
        'year_month' => '2026-04',
        'status' => 'paid',
    ]);

    $payload = [
        'user_id' => $target->id,
        'date' => '2026-04-25',
        'clock_in_at' => '2026-04-25 09:00:00',
        'clock_out_at' => '2026-04-25 18:00:00',
        'status' => 'normal',
    ];
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/attendance', $payload);
    $response->assertStatus(409);
    expect($response->json('error_code'))->toBe('PAYROLL_LOCKED');
});
