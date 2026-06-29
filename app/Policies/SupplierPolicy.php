<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    /**
     * Suppliers are an internal/operational entity — agents and customers
     * never need direct access to supplier data, only admins do.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.delete');
    }
}
