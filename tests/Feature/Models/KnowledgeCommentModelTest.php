<?php

use App\Models\KnowledgeComment;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Schema parity: KnowledgeComment model (Plan 01 / Task 12).
 */

function makeKnowledgeDocument(): KnowledgeDocument
{
    $uploader = User::factory()->create();

    return KnowledgeDocument::create([
        'title'       => 'Guide',
        'description' => 'An onboarding guide',
        'category'    => 'general',
        'file_path'   => 'knowledge/guide.pdf',
        'file_name'   => 'guide.pdf',
        'file_size'   => 1024,
        'file_type'   => 'application/pdf',
        'uploaded_by' => $uploader->id,
    ]);
}

it('mass-assigns all fillable fields', function () {
    $doc = makeKnowledgeDocument();
    $user = User::factory()->create();

    $comment = KnowledgeComment::create([
        'knowledge_document_id' => $doc->id,
        'user_id'               => $user->id,
        'content'               => 'Helpful doc',
    ]);

    $fresh = $comment->fresh();
    expect($fresh->knowledge_document_id)->toBe($doc->id)
        ->and($fresh->user_id)->toBe($user->id)
        ->and($fresh->content)->toBe('Helpful doc');
});

it('document() relation resolves to the parent KnowledgeDocument', function () {
    $doc = makeKnowledgeDocument();
    $user = User::factory()->create();

    $comment = KnowledgeComment::create([
        'knowledge_document_id' => $doc->id,
        'user_id'               => $user->id,
        'content'               => 'c',
    ]);

    expect($comment->document)->toBeInstanceOf(KnowledgeDocument::class)
        ->and($comment->document->id)->toBe($doc->id);
});

it('user() relation resolves to the author', function () {
    $doc = makeKnowledgeDocument();
    $user = User::factory()->create();

    $comment = KnowledgeComment::create([
        'knowledge_document_id' => $doc->id,
        'user_id'               => $user->id,
        'content'               => 'c',
    ]);

    expect($comment->user)->toBeInstanceOf(User::class)
        ->and($comment->user->id)->toBe($user->id);
});
