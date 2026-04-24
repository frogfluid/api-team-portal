<?php

use App\Models\RemoteWorkRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('mass-assigns all fillable fields', function () {
    $user = User::factory()->create();
    $approver = User::factory()->create();

    $req = RemoteWorkRequest::create([
        'user_id'           => $user->id,
        'region'            => 'overseas',
        'start_date'        => '2026-05-01',
        'end_date'          => '2026-05-10',
        'reason'            => 'Conference',
        'deliverables'      => 'Slides',
        'work_environment'  => 'Hotel wifi',
        'status'            => 'approved',
        'approved_by'       => $approver->id,
        'approved_at'       => now(),
        'rejection_reason'  => null,
    ]);

    $fresh = $req->fresh();
    expect($fresh->user_id)->toBe($user->id)
        ->and($fresh->region)->toBe('overseas')
        ->and($fresh->reason)->toBe('Conference')
        ->and($fresh->deliverables)->toBe('Slides')
        ->and($fresh->work_environment)->toBe('Hotel wifi')
        ->and($fresh->status)->toBe('approved')
        ->and($fresh->approved_by)->toBe($approver->id);
});

it('casts dates and approved_at correctly', function () {
    $req = RemoteWorkRequest::factory()->create([
        'start_date'  => '2026-06-01',
        'end_date'    => '2026-06-05',
        'status'      => 'approved',
        'approved_at' => '2026-06-01 12:34:56',
    ]);

    $fresh = $req->fresh();
    expect($fresh->start_date)->toBeInstanceOf(Carbon::class)
        ->and($fresh->start_date->toDateString())->toBe('2026-06-01')
        ->and($fresh->end_date)->toBeInstanceOf(Carbon::class)
        ->and($fresh->end_date->toDateString())->toBe('2026-06-05')
        ->and($fresh->approved_at)->toBeInstanceOf(Carbon::class);
});

it('scope approved returns only approved requests', function () {
    $approved = RemoteWorkRequest::factory()->create(['status' => 'approved']);
    RemoteWorkRequest::factory()->create(['status' => 'pending']);
    RemoteWorkRequest::factory()->create(['status' => 'rejected']);

    $results = RemoteWorkRequest::approved()->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($approved->id);
});

it('scope forUser filters by user_id', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $mine = RemoteWorkRequest::factory()->create(['user_id' => $u1->id]);
    RemoteWorkRequest::factory()->create(['user_id' => $u2->id]);

    $results = RemoteWorkRequest::forUser($u1->id)->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($mine->id);
});

it('scope coversDate returns requests spanning given date (inclusive boundaries)', function () {
    $r = RemoteWorkRequest::factory()->create([
        'start_date' => '2026-07-10',
        'end_date'   => '2026-07-20',
    ]);

    // Inside window
    expect(RemoteWorkRequest::coversDate(Carbon::parse('2026-07-15'))->pluck('id'))
        ->toContain($r->id);
    // On start boundary
    expect(RemoteWorkRequest::coversDate(Carbon::parse('2026-07-10'))->pluck('id'))
        ->toContain($r->id);
    // On end boundary
    expect(RemoteWorkRequest::coversDate(Carbon::parse('2026-07-20'))->pluck('id'))
        ->toContain($r->id);
    // Outside (before)
    expect(RemoteWorkRequest::coversDate(Carbon::parse('2026-07-09'))->pluck('id'))
        ->not->toContain($r->id);
    // Outside (after)
    expect(RemoteWorkRequest::coversDate(Carbon::parse('2026-07-21'))->pluck('id'))
        ->not->toContain($r->id);
});

it('isDomestic / isOverseas / isPending predicates', function () {
    $dom = RemoteWorkRequest::factory()->create(['region' => 'domestic', 'status' => 'pending']);
    $ovr = RemoteWorkRequest::factory()->create(['region' => 'overseas', 'status' => 'approved']);

    expect($dom->isDomestic())->toBeTrue()
        ->and($dom->isOverseas())->toBeFalse()
        ->and($dom->isPending())->toBeTrue();

    expect($ovr->isDomestic())->toBeFalse()
        ->and($ovr->isOverseas())->toBeTrue()
        ->and($ovr->isPending())->toBeFalse();
});

it('region_label and status_label accessors return expected strings', function () {
    $dom = RemoteWorkRequest::factory()->create(['region' => 'domestic', 'status' => 'pending']);
    $ovr = RemoteWorkRequest::factory()->create(['region' => 'overseas', 'status' => 'approved']);
    $rej = RemoteWorkRequest::factory()->create(['region' => 'domestic', 'status' => 'rejected']);

    expect($dom->region_label)->toBe('Remote - Domestic')
        ->and($ovr->region_label)->toBe('Remote - Overseas')
        ->and($dom->status_label)->toBe('Pending')
        ->and($ovr->status_label)->toBe('Approved')
        ->and($rej->status_label)->toBe('Rejected');
});
