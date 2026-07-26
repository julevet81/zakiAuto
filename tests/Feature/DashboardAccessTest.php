<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_cannot_access_dashboard_without_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Create an agent user
        $agentUser = User::create([
            'name' => 'Test Agent',
            'email' => 'agent@zaki.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $agentUser->assignRole('agent');

        Sanctum::actingAs($agentUser);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_income_ignores_the_first_order_for_each_car(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::where('email', 'superadmin@zaki.com')->firstOrFail();
        Sanctum::actingAs($user);

        $supplier = Supplier::create([
            'name' => 'Supplier',
            'phone' => '0555555555',
            'email' => 'supplier-dashboard@test.com',
        ]);

        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 1,
            'status' => 'partial',
        ]);

        $firstCustomer = Customer::create([
            'name' => 'First Customer',
            'phone' => '0500000001',
            'email' => 'first-dashboard@test.com',
        ]);

        $realCustomer = Customer::create([
            'name' => 'Real Customer',
            'phone' => '0500000002',
            'email' => 'real-dashboard@test.com',
        ]);

        $firstOrderOnlyCar = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'manufacture_year' => 2022,
            'foreign_purchase_price' => 7000,
            'sale_price' => 10000,
            'status' => Car::STATUS_SHIPPING,
        ]);

        $soldCar = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Kia',
            'model' => 'Sportage',
            'manufacture_year' => 2023,
            'foreign_purchase_price' => 9000,
            'sale_price' => 15000,
            'status' => Car::STATUS_SOLD,
        ]);

        Order::create([
            'order_number' => 'ORD-FIRST-ONLY',
            'customer_id' => $firstCustomer->id,
            'car_id' => $firstOrderOnlyCar->id,
            'status' => Order::STATUS_SHIPPING,
            'remaining_amount' => 10000,
        ]);

        Order::create([
            'order_number' => 'ORD-SOLD-FIRST',
            'customer_id' => $firstCustomer->id,
            'car_id' => $soldCar->id,
            'status' => Order::STATUS_SHIPPING,
            'remaining_amount' => 15000,
        ]);

        Order::create([
            'order_number' => 'ORD-SOLD-REAL',
            'customer_id' => $realCustomer->id,
            'car_id' => $soldCar->id,
            'status' => Order::STATUS_SOLD,
            'remaining_amount' => 15000,
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.orders_count', 1);
        $response->assertJsonPath('data.total_sales', 15000);
        $response->assertJsonPath('data.total_purchase_cost', 9000);
        $response->assertJsonPath('data.total_profit', 6000);
    }
}
