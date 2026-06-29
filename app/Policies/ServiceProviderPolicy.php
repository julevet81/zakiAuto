<?php

namespace App\Policies;

use App\Models\ServiceProviderModel;
use App\Models\User;

class ServiceProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('service_providers.view');
    }

    public function view(User $user, ServiceProviderModel $serviceProvider): bool
    {
        return $user->can('service_providers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('service_providers.create');
    }

    public function update(User $user, ServiceProviderModel $serviceProvider): bool
    {
        return $user->can('service_providers.update');
    }

    public function delete(User $user, ServiceProviderModel $serviceProvider): bool
    {
        return $user->can('service_providers.delete');
    }
}
