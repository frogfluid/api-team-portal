<?php

use App\Models\AuditLog;
use App\Models\MonthlyMessage;
use App\Models\MonthlyMessageComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Schema parity: MonthlyMessage model (Plan 01 / Task 12).
 */

it('mass-assigns all fillable fields', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();

    $msg = MonthlyMessage::create([
        'user_id'      => $user->id,
        'author_id'    => $author->id,
        'target_month' => '2026-04-01',
        'review'       => 'Great month',
        'goals'        => ['a', 'b'],
        'confirmed_at' => '2026-04-30 12:00:00',
        'response'     => 'Thanks',
    ]);

    $fresh = $msg->fresh();
    expect($fresh->user_id)->toBe($user->id)
        ->and($fresh->author_id)->toBe($author->id)
        ->and($fresh->review)->toBe('Great month')
        ->and($fresh->response)->toBe('Thanks');
});

it('casts goals to array, target_month to date, confirmed_at to datetime', function () {
    $msg = MonthlyMessage::factory()->create([
        'target_month' => '2026-04-01',
        'goals'        => ['one', 'two'],
        'confirmed_at' => '2026-04-30 09:00:00',
    ]);

    $fresh = $msg->fresh();
    expect($fresh->goals)->toBeArray()->toBe(['one', 'two'])
        ->and($fresh->target_month)->toBeInstanceOf(Carbon::class)
        ->and($fresh->target_month->toDateString())->toBe('2026-04-01')
        ->and($fresh->confirmed_at)->toBeInstanceOf(Carbon::class);
});

it('user(), author(), and comments() relations resolve', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();
    $commenter = User::factory()->create();

    $msg = MonthlyMessage::factory()->create([
        'user_id'   => $user->id,
        'author_id' => $author->id,
    ]);

    $c1 = MonthlyMessageComment::create([
        'monthly_message_id' => $msg->id,
        'author_id'          => $commenter->id,
        'body'               => 'first',
    ]);

    expect($msg->user)->toBeInstanceOf(User::class)->and($msg->user->id)->toBe($user->id);
    expect($msg->author)->toBeInstanceOf(User::class)->and($msg->author->id)->toBe($author->id);
    expect($msg->comments)->toHaveCount(1)
        ->and($msg->comments->first()->id)->toBe($c1->id);
});

it('scopeUnconfirmed / scopeConfirmed / scopeForUser / scopeForMonth filter correctly', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $pending = MonthlyMessage::factory()->create([
        'user_id'      => $u1->id,
        'target_month' => '2026-04-01',
        'confirmed_at' => null,
    ]);
    $done = MonthlyMessage::factory()->create([
        'user_id'      => $u1->id,
        'target_month' => '2026-03-01',
        'confirmed_at' => '2026-03-31 10:00:00',
    ]);
    $other = MonthlyMessage::factory()->create([
        'user_id'      => $u2->id,
        'target_month' => '2026-04-01',
    ]);

    expect(MonthlyMessage::unconfirmed()->pluck('id')->all())
        ->toContain($pending->id)
        ->not->toContain($done->id);

    expect(MonthlyMessage::confirmed()->pluck('id')->all())
        ->toContain($done->id)
        ->not->toContain($pending->id);

    $forU1 = MonthlyMessage::forUser($u1->id)->pluck('id')->all();
    expect($forU1)->toContain($pending->id, $done->id)
        ->not->toContain($other->id);

    $apr = MonthlyMessage::forMonth('2026-04-01')->pluck('id')->all();
    expect($apr)->toContain($pending->id, $other->id)
        ->not->toContain($done->id);
});

it('isConfirmed() predicate reflects confirmed_at', function () {
    $pending = MonthlyMessage::factory()->create(['confirmed_at' => null]);
    $done = MonthlyMessage::factory()->create(['confirmed_at' => now()]);

    expect($pending->isConfirmed())->toBeFalse()
        ->and($done->isConfirmed())->toBeTrue();
});

it('target_month_label accessor formats as "F Y"', function () {
    $msg = MonthlyMessage::factory()->create(['target_month' => '2026-04-01']);
    expect($msg->target_month_label)->toBe('April 2026');
});

it('status_badge accessor returns confirmed or pending branches', function () {
    $pending = MonthlyMessage::factory()->create(['confirmed_at' => null]);
    $done = MonthlyMessage::factory()->create(['confirmed_at' => now()]);

    $pBadge = $pending->status_badge;
    $dBadge = $done->status_badge;

    expect($pBadge)->toBeArray()
        ->and($pBadge)->toHaveKeys(['class', 'label'])
        ->and($pBadge['label'])->toBe('Pending')
        ->and($pBadge['class'])->toContain('amber');

    expect($dBadge)->toBeArray()
        ->and($dBadge['label'])->toBe('Confirmed')
        ->and($dBadge['class'])->toContain('emerald');
});

it('Auditable trait emits an AuditLog row on create when authenticated', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $msg = MonthlyMessage::factory()->create([
        'author_id' => $actor->id,
    ]);

    $log = AuditLog::where('auditable_type', MonthlyMessage::class)
        ->where('auditable_id', $msg->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($actor->id);
});
