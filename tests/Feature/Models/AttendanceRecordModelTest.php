<?php

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepts on_leave status', function () {
    $user = User::factory()->create();
    $record = AttendanceRecord::create([
        'user_id' => $user->id,
        'date' => '2026-04-20',
        'status' => 'on_leave',
    ]);

    expect($record->fresh()->status)->toBe('on_leave');
});

it('accepts dedication status', function () {
    $user = User::factory()->create();
    $record = AttendanceRecord::create([
        'user_id' => $user->id,
        'date' => '2026-04-20',
        'status' => 'dedication',
    ]);

    expect($record->fresh()->status)->toBe('dedication');
});

it('accepts is_auto_clocked_out flag', function () {
    $user = User::factory()->create();
    $record = AttendanceRecord::create([
        'user_id' => $user->id,
        'date' => '2026-04-20',
        'status' => 'normal',
        'is_auto_clocked_out' => true,
    ]);

    expect($record->fresh()->is_auto_clocked_out)->toBeTrue();
});
