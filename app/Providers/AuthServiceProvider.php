<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\AgentTransaction;
use App\Models\Batch;
use App\Models\Car;
use App\Models\ContainerOpener;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ServiceProviderModel;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Policies\AgentPolicy;
use App\Policies\AgentTransactionPolicy;
use App\Policies\BatchPolicy;
use App\Policies\CarPolicy;
use App\Policies\ContainerOpenerPolicy;
use App\Policies\CustomerPaymentPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ServiceProviderPolicy;
use App\Policies\SettingPolicy;
use App\Policies\SupplierPaymentPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
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
        SupplierPayment::class => SupplierPaymentPolicy::class,
        Customer::class => CustomerPolicy::class,
        Agent::class => AgentPolicy::class,
        Order::class => OrderPolicy::class,
        CustomerPayment::class => CustomerPaymentPolicy::class,
        AgentTransaction::class => AgentTransactionPolicy::class,
        Expense::class => ExpensePolicy::class,
        Invoice::class => InvoicePolicy::class,
        Setting::class => SettingPolicy::class,
        User::class => UserPolicy::class,
        // Document has no dedicated policy — it's authorized entirely
        // through CarPolicy on its parent Car (see DocumentController).
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
