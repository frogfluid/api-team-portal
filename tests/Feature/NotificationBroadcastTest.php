<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\SystemMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(UserRole $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role->value,
            'is_active' => true,
        ], $overrides));
    }

    public function test_admin_can_broadcast_to_non_admin_users(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);
        $manager = $this->createUser(UserRole::MANAGER);
        $member = $this->createUser(UserRole::MEMBER);
        $intern = $this->createUser(UserRole::INTERN);

        $response = $this->actingAs($admin)->post(route('app.notifications.broadcast'), [
            'title' => 'Office Notice',
            'message' => 'Please submit reports before 6 PM.',
            'audience' => 'non_admin',
            'as_banner' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('notifications', [
            'type' => SystemMessageNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
        ]);

        foreach ([$manager, $member, $intern] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'type' => SystemMessageNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $recipient->id,
            ]);
        }
    }

    public function test_manager_all_active_broadcast_still_excludes_admin_accounts(): void
    {
        $manager = $this->createUser(UserRole::MANAGER);
        $admin = $this->createUser(UserRole::ADMIN);
        $member = $this->createUser(UserRole::MEMBER);

        $response = $this->actingAs($manager)->post(route('app.notifications.broadcast'), [
            'title' => 'Sprint Reminder',
            'message' => 'Standup starts at 10:00.',
            'audience' => 'all_active',
            'as_banner' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('notifications', [
            'type' => SystemMessageNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => SystemMessageNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $member->id,
        ]);
    }

    public function test_member_cannot_broadcast_notifications(): void
    {
        $member = $this->createUser(UserRole::MEMBER);

        $response = $this->actingAs($member)->post(route('app.notifications.broadcast'), [
            'title' => 'Invalid',
            'message' => 'No permission',
            'audience' => 'non_admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_notifications_index_returns_banner_payload(): void
    {
        $member = $this->createUser(UserRole::MEMBER);

        Notification::sendNow([$member], new SystemMessageNotification(
            'General Update',
            'This is a plain notification.',
            null,
            ['is_banner' => false]
        ));

        Notification::sendNow([$member], new SystemMessageNotification(
            'Banner Update',
            'This message should be shown as banner.',
            '/app/dashboard',
            ['is_banner' => true]
        ));

        $response = $this->actingAs($member)->getJson(route('app.notifications.index'));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 2);
        $response->assertJsonPath('banner.data.title', 'Banner Update');
        $response->assertJsonPath('banner.data.is_banner', true);
    }
}
