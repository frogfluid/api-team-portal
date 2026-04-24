<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('can create a user with responsibilities', function () {
    $user = User::factory()->create([
        'responsibilities' => ['Lead backend', 'Mentor interns'],
    ]);

    expect($user->fresh()->responsibilities)->toBe(['Lead backend', 'Mentor interns']);
});

it('no longer has an INTERN role value', function () {
    $values = collect(UserRole::cases())->pluck('value')->all();

    expect($values)->not->toContain('intern');
    expect(defined(UserRole::class.'::INTERN'))->toBeFalse();
});

it('honors the MEMBER role label', function () {
    expect(UserRole::MEMBER->value)->toBe('member');
});
