<?php

use App\Models\MonthlyMessage;
use App\Models\MonthlyMessageComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Schema parity: MonthlyMessageComment model (Plan 01 / Task 12).
 */

it('mass-assigns all fillable fields', function () {
    $msg = MonthlyMessage::factory()->create();
    $author = User::factory()->create();

    $comment = MonthlyMessageComment::create([
        'monthly_message_id' => $msg->id,
        'author_id'          => $author->id,
        'body'               => 'nice',
    ]);

    $fresh = $comment->fresh();
    expect($fresh->monthly_message_id)->toBe($msg->id)
        ->and($fresh->author_id)->toBe($author->id)
        ->and($fresh->body)->toBe('nice');
});

it('message() relation resolves to the parent MonthlyMessage', function () {
    $msg = MonthlyMessage::factory()->create();
    $author = User::factory()->create();

    $comment = MonthlyMessageComment::create([
        'monthly_message_id' => $msg->id,
        'author_id'          => $author->id,
        'body'               => 'b',
    ]);

    expect($comment->message)->toBeInstanceOf(MonthlyMessage::class)
        ->and($comment->message->id)->toBe($msg->id);
});

it('author() relation resolves to the author user', function () {
    $msg = MonthlyMessage::factory()->create();
    $author = User::factory()->create();

    $comment = MonthlyMessageComment::create([
        'monthly_message_id' => $msg->id,
        'author_id'          => $author->id,
        'body'               => 'b',
    ]);

    expect($comment->author)->toBeInstanceOf(User::class)
        ->and($comment->author->id)->toBe($author->id);
});
