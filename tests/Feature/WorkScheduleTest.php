<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role->value,
            'is_active' => true,
        ]);
    }

    private function createSchedule(User $owner, array $overrides = []): WorkSchedule
    {
        $ownerTimezone = (string) data_get($owner->preferences, 'workspace.timezone', 'Asia/Tokyo');

        $startAt = $overrides['start_at'] ?? Carbon::now($ownerTimezone)->addDay()->setHour(9)->setMinute(0)->setSecond(0);
        if (!$startAt instanceof Carbon) {
            $startAt = Carbon::parse((string) $startAt, $ownerTimezone);
        }
        $startAt = $startAt->copy()->utc();

        $endAt = $overrides['end_at'] ?? Carbon::now($ownerTimezone)->addDay()->setHour(18)->setMinute(0)->setSecond(0);
        if (!$endAt instanceof Carbon) {
            $endAt = Carbon::parse((string) $endAt, $ownerTimezone);
        }
        $endAt = $endAt->copy()->utc();

        unset($overrides['start_at'], $overrides['end_at']);

        return WorkSchedule::create(array_merge([
            'user_id' => $owner->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'note' => 'Test schedule',
            'status' => 'pending',
            'type' => 'work',
            'all_day' => false,
        ], $overrides));
    }

    // ─── View/Edit Permission Matrix ───

    public function test_member_can_view_own_schedule(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $this->assertTrue($member->can('view', $schedule));
    }

    public function test_member_can_view_others_schedule(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $other = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($other);

        $this->assertTrue($member->can('view', $schedule));
    }

    public function test_member_can_update_own_schedule(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $this->assertTrue($member->can('update', $schedule));
    }

    public function test_member_cannot_update_others_schedule(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $other = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($other);

        $this->assertFalse($member->can('update', $schedule));
    }

    public function test_member_can_delete_own_schedule(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $this->assertTrue($member->can('delete', $schedule));
    }

    public function test_member_cannot_delete_others_schedule(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $other = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($other);

        $this->assertFalse($member->can('delete', $schedule));
    }

    public function test_manager_can_update_others_schedule(): void
    {
        $manager = $this->createUser(UserRole::MANAGER);
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $this->assertTrue($manager->can('update', $schedule));
    }

    public function test_manager_can_delete_others_schedule(): void
    {
        $manager = $this->createUser(UserRole::MANAGER);
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $this->assertTrue($manager->can('delete', $schedule));
    }

    public function test_admin_can_update_any_schedule(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $this->assertTrue($admin->can('update', $schedule));
    }

    public function test_admin_can_delete_any_schedule(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $this->assertTrue($admin->can('delete', $schedule));
    }

    public function test_intern_cannot_update_others_schedule(): void
    {
        $intern = $this->createUser(UserRole::INTERN);
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $this->assertFalse($intern->can('update', $schedule));
    }

    // ─── Conflict Detection ───

    public function test_schedule_store_detects_conflict(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $this->createSchedule($member, [
            'start_at' => now()->addDay()->setHour(9),
            'end_at' => now()->addDay()->setHour(18),
        ]);

        $response = $this->actingAs($member)->post(route('app.schedules.store'), [
            'start_at' => now()->addDay()->setHour(10)->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->setHour(12)->format('Y-m-d\TH:i'),
            'type' => 'work',
            'all_day' => false,
            'note' => 'Overlapping schedule',
            'repeat_weeks' => 1,
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        $response->assertSessionHasErrors('start_at');
    }

    public function test_schedule_store_allows_non_conflicting(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $this->createSchedule($member, [
            'start_at' => now()->addDay()->setHour(9),
            'end_at' => now()->addDay()->setHour(12),
        ]);

        $response = $this->actingAs($member)->post(route('app.schedules.store'), [
            'start_at' => now()->addDay()->setHour(13)->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->setHour(18)->format('Y-m-d\TH:i'),
            'type' => 'work',
            'all_day' => false,
            'note' => 'Non-overlapping schedule',
            'repeat_weeks' => 1,
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_schedule_store_rejects_dates_before_today(): void
    {
        $member = $this->createUser(UserRole::MEMBER);

        $response = $this->actingAs($member)->post(route('app.schedules.store'), [
            'start_at' => now()->subDay()->setHour(9)->format('Y-m-d\TH:i'),
            'end_at' => now()->subDay()->setHour(10)->format('Y-m-d\TH:i'),
            'type' => 'work',
            'all_day' => false,
            'note' => 'Past schedule',
            'repeat_weeks' => 1,
        ]);

        $response->assertSessionHasErrors('start_at');
    }

    public function test_schedule_index_filters_by_keyword(): void
    {
        $member = $this->createUser(UserRole::MEMBER);

        $match = $this->createSchedule($member, [
            'note' => 'Focus planning block',
        ]);

        $this->createSchedule($member, [
            'note' => 'General execution block',
        ]);

        $response = $this->actingAs($member)
            ->get(route('app.schedules.index', ['q' => 'planning']));

        $response->assertOk();
        $response->assertViewHas('schedules', function ($schedules) use ($match) {
            return $schedules->total() === 1
                && (int) $schedules->first()->id === (int) $match->id;
        });
    }

    public function test_schedule_index_hides_actions_for_past_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00', 'UTC'));

        try {
            $member = User::factory()->create([
                'role' => UserRole::MEMBER->value,
                'is_active' => true,
                'preferences' => [
                    'workspace' => [
                        'timezone' => 'UTC',
                    ],
                ],
            ]);

            $pastSchedule = $this->createSchedule($member, [
                'start_at' => Carbon::parse('2026-03-09 09:00:00', 'UTC'),
                'end_at' => Carbon::parse('2026-03-09 18:00:00', 'UTC'),
            ]);

            $futureSchedule = $this->createSchedule($member, [
                'start_at' => Carbon::parse('2026-03-11 09:00:00', 'UTC'),
                'end_at' => Carbon::parse('2026-03-11 18:00:00', 'UTC'),
            ]);

            $response = $this->actingAs($member)->get(route('app.schedules.index'));

            $response->assertOk();
            $response->assertSee('Locked');
            $response->assertDontSee(route('app.schedules.edit', $pastSchedule), false);
            $response->assertDontSee(route('app.schedules.destroy', $pastSchedule), false);
            $response->assertSee(route('app.schedules.edit', $futureSchedule), false);
            $response->assertSee(route('app.schedules.destroy', $futureSchedule), false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_all_day_event_maps_to_single_calendar_day_in_user_timezone(): void
    {
        $member = $this->createUser(UserRole::MEMBER);

        $this->createSchedule($member, [
            'all_day' => true,
            'start_at' => Carbon::parse('2026-03-25 15:00:00', 'UTC'),
            'end_at' => Carbon::parse('2026-03-26 14:59:59', 'UTC'),
        ]);

        $response = $this->actingAs($member)
            ->getJson(route('app.schedules.events', [
                'start' => '2026-03-01T00:00:00Z',
                'end' => '2026-04-01T00:00:00Z',
                'user_ids' => [$member->id],
            ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'allDay' => true,
            'start' => '2026-03-26',
            'end' => '2026-03-27',
        ]);
    }

    public function test_work_all_day_legacy_multi_day_range_renders_as_single_day(): void
    {
        $member = $this->createUser(UserRole::MEMBER);

        $this->createSchedule($member, [
            'type' => 'work',
            'all_day' => true,
            // Legacy bad payload: stored as 2 local days (2026-02-19 ~ 2026-02-20 in Asia/Tokyo)
            'start_at' => Carbon::parse('2026-02-18 15:00:00', 'UTC'),
            'end_at' => Carbon::parse('2026-02-20 14:59:59', 'UTC'),
        ]);

        $response = $this->actingAs($member)
            ->getJson(route('app.schedules.events', [
                'start' => '2026-02-01T00:00:00Z',
                'end' => '2026-03-01T00:00:00Z',
                'user_ids' => [$member->id],
            ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'allDay' => true,
            'start' => '2026-02-19',
            'end' => '2026-02-20',
        ]);
    }

    public function test_leave_all_day_multi_day_range_keeps_multi_day_rendering(): void
    {
        $member = $this->createUser(UserRole::MEMBER);

        $this->createSchedule($member, [
            'type' => 'leave',
            'all_day' => true,
            // 2 local days in Asia/Tokyo (2026-02-19 ~ 2026-02-20)
            'start_at' => Carbon::parse('2026-02-18 15:00:00', 'UTC'),
            'end_at' => Carbon::parse('2026-02-20 14:59:59', 'UTC'),
        ]);

        $response = $this->actingAs($member)
            ->getJson(route('app.schedules.events', [
                'start' => '2026-02-01T00:00:00Z',
                'end' => '2026-03-01T00:00:00Z',
                'user_ids' => [$member->id],
            ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'allDay' => true,
            'start' => '2026-02-19',
            'end' => '2026-02-21',
        ]);
    }

    public function test_schedule_store_returns_events_payload_for_json_requests(): void
    {
        $member = $this->createUser(UserRole::MEMBER);

        $response = $this->actingAs($member)
            ->postJson(route('app.schedules.store'), [
                'start_at' => now()->addDay()->setHour(9)->format('Y-m-d\TH:i'),
                'end_at' => now()->addDay()->setHour(12)->format('Y-m-d\TH:i'),
                'type' => 'work',
                'all_day' => false,
                'note' => 'JSON create',
                'repeat_weeks' => 1,
            ]);

        $response->assertCreated();
        $response->assertJsonCount(1, 'events');
        $response->assertJsonPath('events.0.extendedProps.note', 'JSON create');
    }

    public function test_work_all_day_update_with_exclusive_end_midnight_remains_single_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-19 01:00:00', 'UTC'));

        try {
            $member = $this->createUser(UserRole::MEMBER);
            $schedule = $this->createSchedule($member, [
                'type' => 'work',
                'all_day' => true,
                'start_at' => Carbon::parse('2026-02-18 15:00:00', 'UTC'),
                'end_at' => Carbon::parse('2026-02-19 14:59:59', 'UTC'),
            ]);

            $response = $this->actingAs($member)
                ->postJson(route('app.schedules.update.post', $schedule), [
                    'type' => 'work',
                    'all_day' => true,
                    // Exclusive end style sent by calendar all-day events.
                    'start_at' => '2026-02-19T00:00',
                    'end_at' => '2026-02-20T00:00',
                    'note' => 'keep-single-day',
                    'repeat_weeks' => 1,
                ]);

            $response->assertOk();
            $response->assertJsonFragment([
                'allDay' => true,
                'start' => '2026-02-19',
                'end' => '2026-02-20',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_past_schedule_cannot_be_deleted_via_json(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $pastSchedule = $this->createSchedule($member, [
            'start_at' => now()->subDay()->setHour(9),
            'end_at' => now()->subDay()->setHour(10),
        ]);

        $response = $this->actingAs($member)
            ->postJson(route('app.schedules.destroy.post', $pastSchedule));

        $response->assertStatus(422);
        $this->assertDatabaseHas('work_schedules', ['id' => $pastSchedule->id]);
    }

    public function test_past_schedule_cannot_be_updated_via_json(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $pastSchedule = $this->createSchedule($member, [
            'start_at' => now()->subDay()->setHour(9),
            'end_at' => now()->subDay()->setHour(10),
        ]);

        $response = $this->actingAs($member)
            ->postJson(route('app.schedules.update.post', $pastSchedule), [
                'type' => 'work',
                'all_day' => false,
                'start_at' => now()->addDay()->setHour(9)->format('Y-m-d\TH:i'),
                'end_at' => now()->addDay()->setHour(10)->format('Y-m-d\TH:i'),
                'note' => 'attempt update',
                'repeat_weeks' => 1,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('work_schedules', [
            'id' => $pastSchedule->id,
            'note' => 'Test schedule',
        ]);
    }

    public function test_events_marks_past_schedule_as_not_deletable(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $pastSchedule = $this->createSchedule($member, [
            'start_at' => now()->subDay()->setHour(9),
            'end_at' => now()->subDay()->setHour(10),
        ]);

        $response = $this->actingAs($member)
            ->getJson(route('app.schedules.events', [
                'start' => now()->subDays(7)->toIso8601String(),
                'end' => now()->addDays(7)->toIso8601String(),
                'user_ids' => [$member->id],
            ]));

        $response->assertOk();
        $response->assertJsonPath('0.id', (string) $pastSchedule->id);
        $response->assertJsonPath('0.extendedProps.can_update', false);
        $response->assertJsonPath('0.extendedProps.can_delete', false);
    }

    public function test_timezone_override_allows_today_actions_in_client_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-26 01:00:00', 'UTC'));

        try {
            $member = User::factory()->create([
                'role' => UserRole::MEMBER->value,
                'is_active' => true,
                'preferences' => [
                    'workspace' => [
                        'timezone' => 'Asia/Tokyo',
                    ],
                ],
            ]);

            $schedule = $this->createSchedule($member, [
                // 2026-02-25 00:00 in America/Los_Angeles
                'start_at' => Carbon::parse('2026-02-25 08:00:00', 'UTC'),
                'end_at' => Carbon::parse('2026-02-25 09:00:00', 'UTC'),
            ]);

            $response = $this->actingAs($member)
                ->getJson(route('app.schedules.events', [
                    'start' => '2026-02-20T00:00:00Z',
                    'end' => '2026-02-28T00:00:00Z',
                    'timezone' => 'America/Los_Angeles',
                    'user_ids' => [$member->id],
                ]));

            $response->assertOk();
            $response->assertJsonPath('0.extendedProps.can_update', true);
            $response->assertJsonPath('0.extendedProps.can_delete', true);

            $deleteResponse = $this->actingAs($member)
                ->postJson(route('app.schedules.destroy.post', $schedule) . '?timezone=America%2FLos_Angeles');
            $deleteResponse->assertOk();
        } finally {
            Carbon::setTestNow();
        }
    }

    // ─── Comments ───

    public function test_schedule_owner_can_post_comment(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $response = $this->actingAs($member)
            ->postJson(route('app.schedules.comments.store', $schedule), [
                'body' => 'My first comment',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['body' => 'My first comment']);
    }

    public function test_manager_can_comment_on_any_schedule(): void
    {
        $manager = $this->createUser(UserRole::MANAGER);
        $member = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($member);

        $response = $this->actingAs($manager)
            ->postJson(route('app.schedules.comments.store', $schedule), [
                'body' => 'Manager feedback',
            ]);

        $response->assertStatus(201);
    }

    public function test_member_cannot_comment_on_others_schedule(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $other = $this->createUser(UserRole::MEMBER);
        $schedule = $this->createSchedule($other);

        $response = $this->actingAs($member)
            ->postJson(route('app.schedules.comments.store', $schedule), [
                'body' => 'Should not be allowed',
            ]);

        $response->assertStatus(403);
    }

    public function test_future_schedule_can_be_deleted_via_json(): void
    {
        $member = $this->createUser(UserRole::MEMBER);
        $futureSchedule = $this->createSchedule($member, [
            'start_at' => now()->addDay()->setHour(9),
            'end_at' => now()->addDay()->setHour(18),
        ]);

        $this->assertDatabaseHas('work_schedules', ['id' => $futureSchedule->id]);

        $response = $this->actingAs($member)
            ->postJson(route('app.schedules.destroy.post', $futureSchedule));

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('deleted_id', (string) $futureSchedule->id);
        $this->assertDatabaseMissing('work_schedules', ['id' => $futureSchedule->id]);
    }
}
