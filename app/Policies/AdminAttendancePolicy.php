<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class AdminAttendancePolicy
{
    public function manage(User $user): bool
    {
        return $user->role === UserRole::ADMIN;
    }
}
