<?php

namespace App\Providers;

use App\Models\Batch;
use App\Models\Car;
use App\Models\ContainerOpener;
use App\Models\ServiceProviderModel;
use App\Models\Supplier;
use App\Policies\BatchPolicy;
use App\Policies\CarPolicy;
use App\Policies\ContainerOpenerPolicy;
use App\Policies\ServiceProviderPolicy;
use App\Policies\SupplierPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * Laravel's policy auto-discovery matches `App\Models\Foo` to
     * `App\Policies\FooPolicy`. This breaks for `ServiceProviderModel`
     * (mapped to `ServiceProviderPolicy`, not `ServiceProviderModelPolicy`),
     * so every policy is registered explicitly here rather than relying on
     * discovery for some and not others — one consistent, predictable list.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Supplier::class => SupplierPolicy::class,
        Car::class => CarPolicy::class,
        ContainerOpener::class => ContainerOpenerPolicy::class,
        ServiceProviderModel::class => ServiceProviderPolicy::class,
        Batch::class => BatchPolicy::class,
        // Additional model => policy mappings are appended here as each
        // subsequent module (Customers, Agents, Orders, Payments...) is
        // built in later phases.
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
