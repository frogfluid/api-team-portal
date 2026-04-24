<?php

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\MessageStar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Schema parity: TeamChat Medium tier (Wave 4).
 *
 * The web migrations add three columns to `messages`:
 *   - pinned_at (timestamp, nullable)
 *   - pinned_by_user_id (foreignId, nullable)
 *   - link_metadata (json, nullable)
 *
 * And two related tables:
 *   - message_stars (user_id, message_id, starred_at)
 *   - message_reactions (message_id, user_id, emoji, timestamps)
 *
 * These tests lock in the Eloquent-side contract for those columns and tables.
 */
it('casts pinned_at and link_metadata on messages', function () {
    $author = User::factory()->create();
    $pinner = User::factory()->create();
    $channel = Channel::create([
        'name' => 'general-'.uniqid(),
        'description' => 'test',
        'type' => 'public',
    ]);

    $message = Message::create([
        'channel_id' => $channel->id,
        'user_id' => $author->id,
        'content' => 'pinned announcement',
        'pinned_at' => now(),
        'pinned_by_user_id' => $pinner->id,
        'link_metadata' => ['title' => 'Example', 'url' => 'https://example.test'],
    ]);

    $fresh = $message->fresh();

    expect($fresh->pinned_at)->not->toBeNull();
    expect($fresh->pinned_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($fresh->pinned_by_user_id)->toBe($pinner->id);
    expect($fresh->link_metadata)->toEqualCanonicalizing(['title' => 'Example', 'url' => 'https://example.test']);
});

it('has a stars relation and a reactions relation', function () {
    $user = User::factory()->create();
    $channel = Channel::create([
        'name' => 'general-'.uniqid(),
        'description' => 'test',
        'type' => 'public',
    ]);

    $message = Message::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'content' => 'hello',
    ]);

    MessageStar::create([
        'user_id' => $user->id,
        'message_id' => $message->id,
    ]);

    MessageReaction::create([
        'message_id' => $message->id,
        'user_id' => $user->id,
        'emoji' => "\u{1F44D}",
    ]);

    $fresh = $message->fresh();

    expect($fresh->stars)->toHaveCount(1);
    expect($fresh->stars->first()->user_id)->toBe($user->id);

    expect($fresh->reactions)->toHaveCount(1);
    expect($fresh->reactions->first()->emoji)->toBe("\u{1F44D}");
});
