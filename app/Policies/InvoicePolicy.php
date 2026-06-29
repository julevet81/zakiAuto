<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Customers hold `invoices.view` (granted in the seeder) so they can
     * see invoices for their own orders. Scoping to "their own" happens
     * in view() below, not viewAny() — same pattern as Order/Customer.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.view')) {
            return false;
        }

        // Staff with full visibility (anyone who can also see all orders)
        // sees every invoice; a plain customer only sees invoices for
        // orders that are actually theirs.
        if ($user->can('orders.view')) {
            return true;
        }

        return $user->customer?->id === $invoice->order?->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.update');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.delete');
    }
}
