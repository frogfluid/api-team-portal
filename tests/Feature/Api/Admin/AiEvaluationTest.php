<?php

use App\Enums\UserRole;
use App\Models\AiEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists all evaluations for admin', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    AiEvaluation::factory()->count(5)->create();

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/evaluations');
    $response->assertOk();
});

it('forbids non-admin (403)', function () {
    $u = User::factory()->create();
    $response = $this->actingAs($u, 'sanctum')->getJson('/api/admin/evaluations');
    $response->assertStatus(403);
    expect($response->json('error_code'))->toBe('FORBIDDEN');
});

it('admin creates an evaluation', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $target = User::factory()->create();

    $payload = [
        'user_id' => $target->id,
        'year_month' => '2026-04',
        'model' => 'claude-opus-4-7',
        'content' => 'good performance summary',
        'score' => 4.5,
        'status' => 'draft',
    ];

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/evaluations', $payload);
    $response->assertCreated();
    expect(AiEvaluation::where('user_id', $target->id)->where('year_month', '2026-04')->exists())->toBeTrue();
});

it('admin update changes content', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $eval = AiEvaluation::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/evaluations/{$eval->id}", ['content' => 'updated content']);

    $response->assertOk();
    expect($eval->fresh()->content)->toBe('updated content');
});
