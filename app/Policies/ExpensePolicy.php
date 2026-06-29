<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Expenses are an internal financial record — no agent/customer
     * permission exists for this resource in the seeder, so this is
     * effectively admin-only, same as suppliers/batches.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can('expenses.update');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->can('expenses.delete');
    }
}
