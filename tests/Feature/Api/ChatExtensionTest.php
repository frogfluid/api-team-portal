<?php

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('surfaces pinned_at, pinned_by_user_id, link_metadata on /api/messages', function () {
    $user = User::factory()->create();
    $channel = Channel::factory()->create(['name' => 'General HQ', 'type' => 'public']);
    $channel->users()->syncWithoutDetaching([$user->id]);

    $msg = Message::create([
        'user_id' => $user->id,
        'channel_id' => $channel->id,
        'content' => 'pinned msg',
        'pinned_at' => Carbon::parse('2026-04-25 10:00:00'),
        'pinned_by_user_id' => $user->id,
        'link_metadata' => ['url' => 'https://example.com', 'title' => 'Example'],
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/messages');

    $response->assertOk();
    $list = $response->json('data') ?? $response->json();
    $found = collect($list)->firstWhere('id', $msg->id);
    expect($found)->not->toBeNull();
    expect($found['pinned_at'])->not->toBeNull();
    expect($found['pinned_by_user_id'])->toBe($user->id);
    expect($found['link_metadata']['url'])->toBe('https://example.com');
});

it('lets a user star and unstar a message', function () {
    $user = User::factory()->create();
    $channel = Channel::factory()->create(['type' => 'public']);
    $channel->users()->syncWithoutDetaching([$user->id]);
    $msg = Message::create(['user_id' => $user->id, 'channel_id' => $channel->id, 'content' => 'x']);

    $star = $this->actingAs($user, 'sanctum')->postJson("/api/messages/{$msg->id}/star");
    $star->assertOk();
    expect(\App\Models\MessageStar::where(['user_id' => $user->id, 'message_id' => $msg->id])->exists())->toBeTrue();

    $unstar = $this->actingAs($user, 'sanctum')->deleteJson("/api/messages/{$msg->id}/star");
    $unstar->assertOk();
    expect(\App\Models\MessageStar::where(['user_id' => $user->id, 'message_id' => $msg->id])->exists())->toBeFalse();
});

it('lets a user react to a message and remove the reaction', function () {
    $user = User::factory()->create();
    $channel = Channel::factory()->create(['type' => 'public']);
    $channel->users()->syncWithoutDetaching([$user->id]);
    $msg = Message::create(['user_id' => $user->id, 'channel_id' => $channel->id, 'content' => 'x']);

    $add = $this->actingAs($user, 'sanctum')->postJson("/api/messages/{$msg->id}/reactions", ['emoji' => '👍']);
    $add->assertOk();
    expect(\App\Models\MessageReaction::where(['user_id' => $user->id, 'message_id' => $msg->id, 'emoji' => '👍'])->exists())->toBeTrue();

    $remove = $this->actingAs($user, 'sanctum')->deleteJson("/api/messages/{$msg->id}/reactions", ['emoji' => '👍']);
    $remove->assertOk();
    expect(\App\Models\MessageReaction::where(['user_id' => $user->id, 'message_id' => $msg->id, 'emoji' => '👍'])->exists())->toBeFalse();
});

it('rejects an empty emoji on reactions with 422 + VALIDATION_ERROR', function () {
    $user = User::factory()->create();
    $channel = Channel::factory()->create(['type' => 'public']);
    $channel->users()->syncWithoutDetaching([$user->id]);
    $msg = Message::create(['user_id' => $user->id, 'channel_id' => $channel->id, 'content' => 'x']);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/messages/{$msg->id}/reactions", []);
    $response->assertStatus(422);
    expect($response->json('error_code'))->toBe('VALIDATION_ERROR');
});
