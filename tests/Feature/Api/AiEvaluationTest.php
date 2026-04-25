<?php

use App\Enums\UserRole;
use App\Models\AiEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists own evaluations for a regular user', function () {
    $u = User::factory()->create();
    AiEvaluation::factory()->for($u)->count(3)->create();
    AiEvaluation::factory()->count(2)->create();

    $response = $this->actingAs($u, 'sanctum')->getJson('/api/evaluations');
    $response->assertOk();
    $list = $response->json('data.evaluations')
        ?? $response->json('evaluations')
        ?? $response->json('data')
        ?? $response->json();
    expect($list)->toHaveCount(3);
});

it('forbids viewing another users evaluation (403 + FORBIDDEN)', function () {
    $u = User::factory()->create();
    $other = User::factory()->create();
    $eval = AiEvaluation::factory()->for($other)->create();

    $response = $this->actingAs($u, 'sanctum')->getJson("/api/evaluations/{$eval->id}");
    $response->assertStatus(403);
    expect($response->json('error_code'))->toBe('FORBIDDEN');
});

it('allows admin to view any evaluation via user-side endpoint', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $eval = AiEvaluation::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')->getJson("/api/evaluations/{$eval->id}");
    $response->assertOk();
});
