<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\JobScope;
use App\Models\User;

class JobScopePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, JobScope $scope): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        return $scope->users()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, JobScope $scope): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, JobScope $scope): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === UserRole::ADMIN;
    }
}
