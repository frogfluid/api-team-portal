<?php

namespace App\Policies;

use App\Models\RemoteWorkRequest;
use App\Models\User;
use App\Enums\UserRole;

class RemoteWorkRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RemoteWorkRequest $req): bool
    {
        return $req->user_id === $user->id || $this->isReviewer($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RemoteWorkRequest $req): bool
    {
        return $req->user_id === $user->id && $req->status === 'pending';
    }

    public function delete(User $user, RemoteWorkRequest $req): bool
    {
        return $req->user_id === $user->id && $req->status === 'pending';
    }

    public function approve(User $user, RemoteWorkRequest $req): bool
    {
        return $this->isReviewer($user) && $req->status === 'pending';
    }

    public function reject(User $user, RemoteWorkRequest $req): bool
    {
        return $this->isReviewer($user) && $req->status === 'pending';
    }

    private function isReviewer(User $user): bool
    {
        return $user->role instanceof UserRole && $user->role->canReview();
    }
}
