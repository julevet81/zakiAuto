<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Any of the three customer-related permissions grants list access;
     * the controller is responsible for actually SCOPING the query
     * (admin sees all, agent sees only their own) — viewAny only answers
     * "is this user allowed to hit this endpoint at all".
     */
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view') || $user->can('customers.view_assigned');
    }

    /**
     * Per-record visibility:
     *   - customers.view            -> admin/super-admin, any customer.
     *   - customers.view_assigned   -> agent, ONLY their own customers.
     *   - a customer account itself -> only their own linked record.
     */
    public function view(User $user, Customer $customer): bool
    {
        if ($user->can('customers.view')) {
            return true;
        }

        if ($user->can('customers.view_assigned')) {
            return $user->agent?->id === $customer->agent_id;
        }

        // A plain customer account may view their own profile record.
        return $user->customer?->id === $customer->id;
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    /**
     * An agent may only update customers assigned to them; an admin may
     * update any customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        if ($user->can('customers.view')) {
            return true;
        }

        if ($user->can('customers.update')) {
            return $user->agent?->id === $customer->agent_id;
        }

        return false;
    }

    /**
     * Deleting a customer is a destructive, admin-only action — no
     * scoped "delete your own customers" exists in the requirements, so
     * agents never get this regardless of assignment.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete');
    }
}
