<?php

namespace App\Policies;

use App\Models\Batch;
use App\Models\User;

class BatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('batches.view');
    }

    public function view(User $user, Batch $batch): bool
    {
        return $user->can('batches.view');
    }

    public function create(User $user): bool
    {
        return $user->can('batches.create');
    }

    public function update(User $user, Batch $batch): bool
    {
        return $user->can('batches.update');
    }

    public function delete(User $user, Batch $batch): bool
    {
        return $user->can('batches.delete');
    }
}
