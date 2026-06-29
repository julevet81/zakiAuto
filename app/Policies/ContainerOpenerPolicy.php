<?php

namespace App\Policies;

use App\Models\ContainerOpener;
use App\Models\User;

class ContainerOpenerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('container_openers.view');
    }

    public function view(User $user, ContainerOpener $containerOpener): bool
    {
        return $user->can('container_openers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('container_openers.create');
    }

    public function update(User $user, ContainerOpener $containerOpener): bool
    {
        return $user->can('container_openers.update');
    }

    public function delete(User $user, ContainerOpener $containerOpener): bool
    {
        return $user->can('container_openers.delete');
    }
}
