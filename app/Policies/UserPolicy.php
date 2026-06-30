<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view') || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.update') || $user->id === $model->id;
    }

    /**
     * users.delete is excluded from the admin role in the seeder
     * (super-admin only) — this re-states that at the Policy layer too,
     * AND adds a safety rail no permission check alone can express:
     * nobody, including a super-admin, may delete their own account
     * through this endpoint (avoids an admin locking themselves out, or
     * a system ending up with zero super-admins).
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users.delete');
    }
}
