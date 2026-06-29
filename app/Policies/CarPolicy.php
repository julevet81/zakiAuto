<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    /**
     * Customers and agents both hold `cars.view` (they need to browse
     * available cars / see the car behind their own orders), so viewAny
     * and view are simple permission checks with no extra scoping here.
     * Anything sensitive on a Car (cost prices, supplier, expenses) is
     * hidden at the Resource layer instead, not the Policy layer, since
     * "can see the car" and "can see its purchase cost" are different
     * questions — see CarResource for the cost-data gating.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('cars.view');
    }

    public function view(User $user, Car $car): bool
    {
        return $user->can('cars.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cars.create');
    }

    public function update(User $user, Car $car): bool
    {
        return $user->can('cars.update');
    }

    public function delete(User $user, Car $car): bool
    {
        return $user->can('cars.delete');
    }
}
