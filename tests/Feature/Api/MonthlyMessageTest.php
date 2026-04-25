<?php

use App\Models\MonthlyMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists own monthly messages, newest first', function () {
    $u = User::factory()->create();
    $other = User::factory()->create();

    $m1 = MonthlyMessage::factory()->create([
        'user_id' => $u->id, 'author_id' => $other->id,
        'target_month' => '2026-04-01',
    ]);
    $m2 = MonthlyMessage::factory()->create([
        'user_id' => $u->id, 'author_id' => $other->id,
        'target_month' => '2026-03-01',
    ]);
    // Another user's message — should not appear in list
    MonthlyMessage::factory()->create([
        'user_id' => $other->id, 'author_id' => $u->id,
        'target_month' => '2026-04-01',
    ]);

    $response = $this->actingAs($u, 'sanctum')->getJson('/api/feedback');
    $response->assertOk();
    $list = $response->json('data.messages') ?? $response->json('messages');
    expect($list)->toHaveCount(2);
    // newest first
    expect($list[0]['id'])->toBe($m1->id);
    expect($list[1]['id'])->toBe($m2->id);
});

it('forbids viewing another users monthly message (403)', function () {
    $u = User::factory()->create();
    $other = User::factory()->create();
    $m = MonthlyMessage::factory()->create(['user_id' => $other->id, 'author_id' => $u->id]);

    $response = $this->actingAs($u, 'sanctum')->getJson("/api/feedback/{$m->id}");
    $response->assertStatus(403);
});

it('shows the message with author + comments', function () {
    $u = User::factory()->create();
    $author = User::factory()->create();
    $m = MonthlyMessage::factory()->create([
        'user_id' => $u->id, 'author_id' => $author->id,
        'review' => 'Great work this month',
    ]);

    $response = $this->actingAs($u, 'sanctum')->getJson("/api/feedback/{$m->id}");
    $response->assertOk();
    $body = $response->json('data.message') ?? $response->json('message');
    expect($body['id'])->toBe($m->id);
    expect($body['review'])->toBe('Great work this month');
    expect($body['author']['id'])->toBe($author->id);
});

it('confirms a message with a response', function () {
    $u = User::factory()->create();
    $other = User::factory()->create();
    $m = MonthlyMessage::factory()->create([
        'user_id' => $u->id, 'author_id' => $other->id,
        'confirmed_at' => null,
    ]);

    $response = $this->actingAs($u, 'sanctum')
        ->postJson("/api/feedback/{$m->id}/confirm", ['response' => 'Thanks for the feedback']);

    $response->assertOk();
    expect($response->json('data.already_confirmed') ?? $response->json('already_confirmed'))->toBeFalse();

    $fresh = $m->fresh();
    expect($fresh->confirmed_at)->not->toBeNull();
    expect($fresh->response)->toBe('Thanks for the feedback');
});

it('rejects empty response with 422 + VALIDATION_ERROR', function () {
    $u = User::factory()->create();
    $other = User::factory()->create();
    $m = MonthlyMessage::factory()->create(['user_id' => $u->id, 'author_id' => $other->id]);

    $response = $this->actingAs($u, 'sanctum')
        ->postJson("/api/feedback/{$m->id}/confirm", ['response' => '']);

    $response->assertStatus(422);
    expect($response->json('error_code'))->toBe('VALIDATION_ERROR');
});

it('returns already_confirmed when re-confirming', function () {
    $u = User::factory()->create();
    $other = User::factory()->create();
    $m = MonthlyMessage::factory()->create([
        'user_id' => $u->id, 'author_id' => $other->id,
        'confirmed_at' => now(),
        'response' => 'Already done',
    ]);

    $response = $this->actingAs($u, 'sanctum')
        ->postJson("/api/feedback/{$m->id}/confirm", ['response' => 'Different now']);

    $response->assertOk();
    expect($response->json('data.already_confirmed') ?? $response->json('already_confirmed'))->toBeTrue();
    // Original response should NOT be overwritten
    expect($m->fresh()->response)->toBe('Already done');
});
