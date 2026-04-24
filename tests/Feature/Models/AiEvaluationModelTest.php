<?php

use App\Models\AiEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Schema parity: AiEvaluation model (Plan 01 / Task 12).
 */

it('mass-assigns all fillable fields', function () {
    $user = User::factory()->create();
    $evaluator = User::factory()->create();

    $eval = AiEvaluation::create([
        'user_id'        => $user->id,
        'evaluator_id'   => $evaluator->id,
        'year_month'     => '2026-04',
        'model'          => 'gpt-4',
        'evaluator_note' => 'Solid work',
        'status'         => 'completed',
        'content'        => 'AI summary here',
        'score'          => 85,
    ]);

    $fresh = $eval->fresh();
    expect($fresh->user_id)->toBe($user->id)
        ->and($fresh->evaluator_id)->toBe($evaluator->id)
        ->and($fresh->year_month)->toBe('2026-04')
        ->and($fresh->model)->toBe('gpt-4')
        ->and($fresh->evaluator_note)->toBe('Solid work')
        ->and($fresh->status)->toBe('completed')
        ->and($fresh->content)->toBe('AI summary here')
        ->and($fresh->score)->toBe(85);
});

it('user() relation resolves to the subject user', function () {
    $user = User::factory()->create();
    $eval = AiEvaluation::factory()->create(['user_id' => $user->id]);

    expect($eval->user)->toBeInstanceOf(User::class)
        ->and($eval->user->id)->toBe($user->id);
});

it('evaluator() relation resolves to the evaluator user', function () {
    $evaluator = User::factory()->create();
    $eval = AiEvaluation::factory()->create(['evaluator_id' => $evaluator->id]);

    expect($eval->evaluator)->toBeInstanceOf(User::class)
        ->and($eval->evaluator->id)->toBe($evaluator->id);
});
