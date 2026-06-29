<?php

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;

class SupplierPaymentPolicy
{
    /**
     * Supplier payments are purely an internal financial record — no
     * scoping needed beyond the plain Spatie permission, since agents and
     * customers never hold any supplier_payments.* permission to begin with.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('supplier_payments.view');
    }

    public function view(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can('supplier_payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplier_payments.create');
    }

    public function update(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can('supplier_payments.update');
    }

    public function delete(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can('supplier_payments.delete');
    }
}
