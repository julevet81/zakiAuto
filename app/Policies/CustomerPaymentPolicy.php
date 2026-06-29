<?php

namespace App\Policies;

use App\Models\CustomerPayment;
use App\Models\User;

class CustomerPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customer_payments.view') || $user->can('customer_payments.view_own');
    }

    /**
     * Per-record visibility:
     *   - customer_payments.view      -> admin/super-admin, any payment.
     *   - customer_payments.view_own  -> the customer who made the payment,
     *                                    OR the agent who collected it.
     */
    public function view(User $user, CustomerPayment $customerPayment): bool
    {
        if ($user->can('customer_payments.view')) {
            return true;
        }

        if ($user->can('customer_payments.view_own')) {
            return $user->customer?->id === $customerPayment->customer_id
                || $user->agent?->id === $customerPayment->agent_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('customer_payments.create');
    }

    public function update(User $user, CustomerPayment $customerPayment): bool
    {
        return $user->can('customer_payments.update');
    }

    public function delete(User $user, CustomerPayment $customerPayment): bool
    {
        return $user->can('customer_payments.delete');
    }
}
