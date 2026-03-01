<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => UserRole::MEMBER->value,
            'is_active' => true,
        ]);
    }

    public function test_user_search_result_links_to_dm_for_other_user(): void
    {
        $actor = $this->createUser('Actor User', 'actor@example.com');
        $colleague = $this->createUser('Colleague User', 'colleague@example.com');

        $response = $this->actingAs($actor)->getJson(route('app.search', ['q' => 'Colleague']));

        $response->assertOk();
        $userResult = collect($response->json())
            ->first(fn($item) => ($item['type'] ?? '') === 'user' && ($item['title'] ?? '') === $colleague->name);

        $this->assertNotNull($userResult);
        $this->assertSame(route('app.chat.dm', $colleague), $userResult['url']);
    }

    public function test_user_search_result_links_to_profile_for_current_user(): void
    {
        $actor = $this->createUser('Self User', 'self@example.com');

        $response = $this->actingAs($actor)->getJson(route('app.search', ['q' => 'Self']));

        $response->assertOk();
        $userResult = collect($response->json())
            ->first(fn($item) => ($item['type'] ?? '') === 'user' && ($item['title'] ?? '') === $actor->name);

        $this->assertNotNull($userResult);
        $this->assertSame(route('app.profile.edit'), $userResult['url']);
    }
}
