<?php

use App\Models\RemoteWorkRequest;
use App\Models\User;
use App\Models\WorkDailyLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('mass-assigns all review workflow fields', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create();

    $log = WorkDailyLog::create([
        'user_id'           => $user->id,
        'work_date'         => '2026-04-10',
        'status'            => 'submitted',
        'submitted_at'      => now(),
        'deliverables'      => 'Implemented feature X',
        'time_blocks'       => [['start' => '09:00', 'end' => '12:00']],
        'communication_log' => 'Standup + 2 slack threads',
        'review_status'     => 'approved',
        'reviewed_by'       => $reviewer->id,
        'reviewed_at'       => now(),
        'review_note'       => 'LGTM',
        'is_revised'        => true,
    ]);

    $fresh = $log->fresh();
    expect($fresh->deliverables)->toBe('Implemented feature X')
        ->and($fresh->time_blocks)->toEqualCanonicalizing([['start' => '09:00', 'end' => '12:00']])
        ->and($fresh->communication_log)->toBe('Standup + 2 slack threads')
        ->and($fresh->review_status)->toBe('approved')
        ->and($fresh->reviewed_by)->toBe($reviewer->id)
        ->and($fresh->review_note)->toBe('LGTM')
        ->and($fresh->is_revised)->toBeTrue();
});

it('casts time_blocks to array', function () {
    $log = WorkDailyLog::factory()->create([
        'time_blocks' => [['start' => '10:00', 'end' => '11:00', 'label' => 'review']],
    ]);

    $fresh = $log->fresh();
    expect($fresh->time_blocks)->toBeArray()
        ->and($fresh->time_blocks[0]['label'])->toBe('review');
});

it('casts is_revised to boolean', function () {
    $log = WorkDailyLog::factory()->create(['is_revised' => 1]);
    expect($log->fresh()->is_revised)->toBeBool()->toBeTrue();

    $log2 = WorkDailyLog::factory()->create(['is_revised' => 0]);
    expect($log2->fresh()->is_revised)->toBeBool()->toBeFalse();
});

it('casts reviewed_at to Carbon', function () {
    $log = WorkDailyLog::factory()->create([
        'reviewed_at' => '2026-04-10 09:30:00',
    ]);
    expect($log->fresh()->reviewed_at)->toBeInstanceOf(Carbon::class);
});

it('does not mass-assign admin_comment', function () {
    $user = User::factory()->create();
    $log = WorkDailyLog::create([
        'user_id'       => $user->id,
        'work_date'     => '2026-04-11',
        'status'        => 'submitted',
        'admin_comment' => 'SHOULD NOT BE SET',
    ]);

    // Column still exists, but is not mass-assignable — so it's null
    expect($log->fresh()->admin_comment)->toBeNull();
});

it('reviewer relation resolves to correct User', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();

    $log = WorkDailyLog::factory()->create([
        'user_id'     => $owner->id,
        'reviewed_by' => $reviewer->id,
    ]);

    expect($log->reviewer->id)->toBe($reviewer->id)
        ->and($log->reviewer->id)->not->toBe($owner->id);
});

it('scopeForMonth returns only logs in the given month', function () {
    $user = User::factory()->create();
    $inside1 = WorkDailyLog::factory()->create(['user_id' => $user->id, 'work_date' => '2026-03-05']);
    $inside2 = WorkDailyLog::factory()->create(['user_id' => $user->id, 'work_date' => '2026-03-28']);
    WorkDailyLog::factory()->create(['user_id' => $user->id, 'work_date' => '2026-02-28']);
    WorkDailyLog::factory()->create(['user_id' => $user->id, 'work_date' => '2026-04-01']);

    $ids = WorkDailyLog::forMonth(2026, 3)->pluck('id')->all();
    expect($ids)->toContain($inside1->id)
        ->toContain($inside2->id)
        ->toHaveCount(2);
});

it('scopeForUser returns only the given user logs', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $mine = WorkDailyLog::factory()->create(['user_id' => $u1->id]);
    WorkDailyLog::factory()->create(['user_id' => $u2->id]);

    $results = WorkDailyLog::forUser($u1->id)->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($mine->id);
});

it('scopeRemote returns only logs covered by an approved RemoteWorkRequest for the same user', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    // Approved RWR for u1 covering 2026-03-10..15
    RemoteWorkRequest::factory()->create([
        'user_id'    => $u1->id,
        'status'     => 'approved',
        'start_date' => '2026-03-10',
        'end_date'   => '2026-03-15',
    ]);

    // Pending RWR for u1 covering 2026-03-20..22
    RemoteWorkRequest::factory()->create([
        'user_id'    => $u1->id,
        'status'     => 'pending',
        'start_date' => '2026-03-20',
        'end_date'   => '2026-03-22',
    ]);

    $matching      = WorkDailyLog::factory()->create(['user_id' => $u1->id, 'work_date' => '2026-03-12']);
    $otherUser     = WorkDailyLog::factory()->create(['user_id' => $u2->id, 'work_date' => '2026-03-12']);
    $nonApprovedLog = WorkDailyLog::factory()->create(['user_id' => $u1->id, 'work_date' => '2026-03-21']);
    $outsideWindow = WorkDailyLog::factory()->create(['user_id' => $u1->id, 'work_date' => '2026-03-16']);

    $ids = WorkDailyLog::remote()->pluck('id')->all();
    expect($ids)->toContain($matching->id)
        ->not->toContain($otherUser->id)
        ->not->toContain($nonApprovedLog->id)
        ->not->toContain($outsideWindow->id)
        ->toHaveCount(1);
});

it('isRemote returns true when covered, false when not, false when fields missing', function () {
    $user = User::factory()->create();
    RemoteWorkRequest::factory()->create([
        'user_id'    => $user->id,
        'status'     => 'approved',
        'start_date' => '2026-03-10',
        'end_date'   => '2026-03-15',
    ]);

    $covered = WorkDailyLog::factory()->create(['user_id' => $user->id, 'work_date' => '2026-03-12']);
    $uncovered = WorkDailyLog::factory()->create(['user_id' => $user->id, 'work_date' => '2026-03-20']);

    expect($covered->isRemote())->toBeTrue()
        ->and($uncovered->isRemote())->toBeFalse();

    $noUser = new WorkDailyLog(['work_date' => '2026-03-12']);
    $noDate = new WorkDailyLog(['user_id' => $user->id]);
    expect($noUser->isRemote())->toBeFalse()
        ->and($noDate->isRemote())->toBeFalse();
});

it('canBeReturned returns true when not yet revised, false otherwise', function () {
    $log1 = WorkDailyLog::factory()->create(['is_revised' => false]);
    $log2 = WorkDailyLog::factory()->create(['is_revised' => true]);

    expect($log1->canBeReturned())->toBeTrue()
        ->and($log2->canBeReturned())->toBeFalse();
});

it('getReviewStatusLabelAttribute returns translated strings for each state', function () {
    $cases = [
        'approved' => 'Approved',
        'flagged'  => 'Flagged as Suspicious',
        'rejected' => 'Rejected - Hours Zeroed',
        'returned' => 'Returned for Revision',
        'pending'  => 'Pending',
    ];

    foreach ($cases as $status => $label) {
        $log = WorkDailyLog::factory()->create(['review_status' => $status]);
        expect($log->review_status_label)->toBe($label);
    }

    // Unknown / fallback => Pending
    $unknown = new WorkDailyLog(['review_status' => 'some-weird-value']);
    expect($unknown->review_status_label)->toBe('Pending');
});
