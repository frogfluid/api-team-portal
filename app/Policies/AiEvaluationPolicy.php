<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AiEvaluation;
use App\Models\User;

class AiEvaluationPolicy
{
    public function view(User $user, AiEvaluation $eval): bool
    {
        return $eval->user_id === $user->id || $this->isAdmin($user);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, AiEvaluation $eval): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, AiEvaluation $eval): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === UserRole::ADMIN;
    }
}
