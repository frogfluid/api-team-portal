<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

uses(RefreshDatabase::class);

it('returns error_code=UNAUTHORIZED on 401 from a protected route', function () {
    $response = $this->getJson('/api/auth/me');
    $response->assertStatus(401);
    expect($response->json('error_code'))->toBe('UNAUTHORIZED');
});

it('returns error_code=NOT_FOUND on 404 from missing model binding', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user, 'sanctum')->getJson('/api/tasks/99999999');
    $response->assertStatus(404);
    expect($response->json('error_code'))->toBe('NOT_FOUND');
});

it('returns error_code=VALIDATION_ERROR on 422 from a missing required field', function () {
    $user = User::factory()->create();
    // POST /api/tasks requires title + owner_id; sending empty body fails validation.
    $response = $this->actingAs($user, 'sanctum')->postJson('/api/tasks', []);
    $response->assertStatus(422);
    expect($response->json('error_code'))->toBe('VALIDATION_ERROR');
    expect($response->json('errors'))->toHaveKey('title');
});

it('returns error_code=PAYROLL_LOCKED with status 409 when PayrollLockedException is thrown', function () {
    \Illuminate\Support\Facades\Route::get('/__test/payroll-locked', function () {
        throw new \App\Exceptions\PayrollLockedException('locked for april');
    })->middleware('api');

    $response = $this->getJson('/__test/payroll-locked');
    $response->assertStatus(409);
    expect($response->json('error_code'))->toBe('PAYROLL_LOCKED');
    expect($response->json('message'))->toBe('locked for april');
});
