<?php

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes is_auto_clocked_out on attendance records', function () {
    $user = User::factory()->create();
    $rec = AttendanceRecord::factory()->for($user)->create([
        'date'                => '2026-04-25',
        'is_auto_clocked_out' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/attendance?from=2026-04-25&to=2026-04-25');

    $response->assertOk();
    $list = $response->json('data.data')
        ?? $response->json('attendance')
        ?? $response->json('records')
        ?? $response->json('data')
        ?? $response->json();
    $found = collect($list)->firstWhere('id', $rec->id);
    expect($found)->not->toBeNull();
    expect($found['is_auto_clocked_out'])->toBeTrue();
});

it('accepts on_leave and dedication when filtering by status', function () {
    $user = User::factory()->create();
    AttendanceRecord::factory()->for($user)->create(['status' => 'on_leave', 'date' => '2026-04-25']);
    AttendanceRecord::factory()->for($user)->create(['status' => 'dedication', 'date' => '2026-04-26']);

    $r1 = $this->actingAs($user, 'sanctum')->getJson('/api/attendance?status=on_leave');
    $r1->assertOk();

    $r2 = $this->actingAs($user, 'sanctum')->getJson('/api/attendance?status=dedication');
    $r2->assertOk();
});
