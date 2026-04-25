<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

uses(RefreshDatabase::class);

it('generates an X-Request-Id when the inbound request omits one', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

    $response->assertOk();
    $id = $response->headers->get('X-Request-Id');
    expect($id)->not->toBeEmpty();
    expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
});

it('echoes a valid inbound X-Request-Id back in the response', function () {
    $user = User::factory()->create();
    $inbound = '11111111-2222-4333-8444-555555555555';
    $response = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-Request-Id' => $inbound])
        ->getJson('/api/auth/me');

    $response->assertOk();
    expect($response->headers->get('X-Request-Id'))->toBe($inbound);
});

it('replaces a malformed inbound X-Request-Id with a fresh UUID', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user, 'sanctum')
        ->withHeaders(['X-Request-Id' => 'not-a-uuid'])
        ->getJson('/api/auth/me');

    $response->assertOk();
    $id = $response->headers->get('X-Request-Id');
    expect($id)->not->toBe('not-a-uuid');
    expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
});
