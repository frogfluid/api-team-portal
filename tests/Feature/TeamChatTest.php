<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamChatTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(UserRole $role = UserRole::MEMBER): User
    {
        return User::factory()->create([
            'role' => $role->value,
            'is_active' => true,
        ]);
    }

    private function createChannel(string $name = 'General HQ', string $type = 'public'): Channel
    {
        return Channel::firstOrCreate(
            ['name' => $name],
            ['description' => 'Test channel', 'type' => $type]
        );
    }

    // ─── Message Sending ───

    public function test_user_can_send_message(): void
    {
        $user = $this->createUser();
        $channel = $this->createChannel();

        $response = $this->actingAs($user)
            ->postJson(route('app.chat.store', $channel), [
                'content' => 'Hello world',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['content' => 'Hello world']);
    }

    public function test_empty_message_without_files_rejected(): void
    {
        $user = $this->createUser();
        $channel = $this->createChannel();

        $response = $this->actingAs($user)
            ->postJson(route('app.chat.store', $channel), [
                'content' => '',
            ]);

        $response->assertStatus(422);
    }

    // ─── Reply Feature ───

    public function test_user_can_reply_to_message(): void
    {
        $user = $this->createUser();
        $channel = $this->createChannel();

        $original = $channel->messages()->create([
            'user_id' => $user->id,
            'content' => 'Original message',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('app.chat.store', $channel), [
                'content' => 'This is a reply',
                'reply_to_id' => $original->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['reply_to_id' => $original->id]);
        $this->assertNotNull($response->json('reply_to'));
        $this->assertEquals('Original message', $response->json('reply_to.content'));
    }

    // ─── Mentions ───

    public function test_user_can_mention_others(): void
    {
        $user = $this->createUser();
        $mentioned = $this->createUser();
        $channel = $this->createChannel();

        $response = $this->actingAs($user)
            ->postJson(route('app.chat.store', $channel), [
                'content' => "@{$mentioned->name} check this out",
                'mention_ids' => [$mentioned->id],
            ]);

        $response->assertStatus(200);
        $mentions = $response->json('mentions');
        $this->assertNotEmpty($mentions);
        $this->assertEquals($mentioned->id, $mentions[0]['id']);
    }

    // ─── Revoke ───

    public function test_user_can_revoke_own_message(): void
    {
        $user = $this->createUser();
        $channel = $this->createChannel();

        $message = $channel->messages()->create([
            'user_id' => $user->id,
            'content' => 'Message to revoke',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('app.chat.revoke', $message));

        $response->assertStatus(200);
        $this->assertTrue($response->json('is_revoked'));
    }

    public function test_user_cannot_revoke_others_message(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $channel = $this->createChannel();

        $message = $channel->messages()->create([
            'user_id' => $other->id,
            'content' => 'Not your message',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('app.chat.revoke', $message));

        $response->assertStatus(403);
    }

    // ─── Executive Board Access ───

    public function test_admin_can_access_executive_board(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);
        $channel = $this->createChannel('Executive Board');

        $response = $this->actingAs($admin)
            ->get(route('app.chat.show', $channel));

        $response->assertStatus(200);
    }

    public function test_member_cannot_access_executive_board(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $channel = $this->createChannel('Executive Board');

        $response = $this->actingAs($member)
            ->get(route('app.chat.show', $channel));

        $response->assertStatus(403);
    }

    // ─── Private DM Access ───

    public function test_dm_channel_restricts_non_participants(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $outsider = $this->createUser();

        $id1 = min($user1->id, $user2->id);
        $id2 = max($user1->id, $user2->id);
        $channel = Channel::create([
            'name' => "dm_{$id1}_{$id2}",
            'description' => 'Direct Message',
            'type' => 'private',
        ]);
        $channel->users()->syncWithoutDetaching([$id1, $id2]);

        $response = $this->actingAs($outsider)
            ->get(route('app.chat.show', $channel));

        $response->assertStatus(403);
    }

    // ─── Unread Count (Read Receipt) ───

    public function test_fetching_messages_updates_last_read(): void
    {
        $user = $this->createUser();
        $channel = $this->createChannel();
        $channel->users()->syncWithoutDetaching([$user->id]);

        $channel->messages()->create([
            'user_id' => $user->id,
            'content' => 'Test message',
        ]);

        $this->actingAs($user)
            ->getJson(route('app.chat.messages', $channel));

        $pivot = $channel->users()->where('users.id', $user->id)->first()?->pivot;
        $this->assertNotNull($pivot?->last_read_at);
    }
}
