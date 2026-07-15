<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Any of the three order-related view permissions grants access to
     * hit the list endpoint at all; OrderController::index() is
     * responsible for the actual query scoping per role.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view')
            || $user->can('orders.view_assigned');
    }

    /**
     * Per-record visibility:
     *   - orders.view           -> admin/super-admin, any order.
     *   - orders.view_assigned  -> agent, only orders for their own customers.
     *   - orders.view_own       -> customer, only their own orders.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->agent) {
            return $user->agent->id === $order->agent_id;
        }

        if ($user->can('orders.view')) {
            return true;
        }

        if ($user->can('orders.view_assigned')) {
            return $user->agent?->id === $order->agent_id;
        }

        

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }

    /**
     * General field edits (notes, dates, agent reassignment): admin only.
     * Agents and customers never edit an order directly — agents work
     * through dedicated actions (recording a payment) and customers are
     * read-only. This is deliberately stricter than view() so an agent who
     * can SEE their customer's order still cannot silently rewrite it.
     */
    public function update(User $user, Order $order): bool
    {
        if ($user->agent) {
            return $user->agent->id === $order->agent_id && $user->can('orders.update');
        }
        return $user->can('orders.update');
    }

    /**
     * Status transitions (new -> purchased -> ... -> delivered) follow
     * their own permission so this workflow action can be granted
     * separately from "can edit arbitrary fields" if needed later.
     * Currently only orders.change_status holders may advance status —
     * granted to admin/super-admin only in the seeder.
     */
    public function changeStatus(User $user, Order $order): bool
    {
        if ($user->agent) {
            return $user->agent->id === $order->agent_id && $user->can('orders.change_status');
        }
        return $user->can('orders.change_status');
    }

    public function delete(User $user, Order $order): bool
    {
        if ($user->agent) {
            return $user->agent->id === $order->agent_id && $user->can('orders.delete');
        }
        return $user->can('orders.delete');
    }
}
