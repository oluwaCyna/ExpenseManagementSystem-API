<?php

namespace App\Policies;

use App\Models\User;

class DashboardPolicy
{
    public function viewDashboard(User $user): bool
    {
        return in_array($user->role, ['Admin', 'Manager']);
    }
}
