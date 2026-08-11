<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Car;
use App\Models\CarExpense;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_lookup_endpoint_returns_car_expenses(): void
    {
        // 1. Create a supplier
        $supplier = Supplier::create([
            'name' => 'Supplier Auto',
        ]);

        // 2. Create a batch
        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 3.5,
            'status' => 'partial',
        ]);

        // 3. Create a car
        $car = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Hyundai',
            'model' => 'Elantra',
            'manufacture_year' => 2021,
            'foreign_purchase_price' => 8000,
            'sale_price' => 12000,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        // 4. Create expenses for the car
        $expense1 = CarExpense::create([
            'car_id' => $car->id,
            'expense_type' => 'Repairs',
            'foreign_amount' => 200,
            'local_amount' => 700,
            'notes' => 'Fixed bumper scratches',
        ]);

        $expense2 = CarExpense::create([
            'car_id' => $car->id,
            'expense_type' => 'Customs',
            'foreign_amount' => 500,
            'local_amount' => 1750,
            'notes' => 'Customs declaration fee',
        ]);

        // 5. Create customer
        $customer = Customer::create([
            'name' => 'Zaki Customer',
            'phone' => '05511122233',
            'email' => 'zaki.cust@example.com',
            'national_id' => '1234567890',
            'passport_no' => 'P1234567',
            'address' => 'Tripoli, Libya',
        ]);

        // 6. Create order linking customer and car
        Order::create([
            'order_number' => 'ORD-ZAKI-99',
            'customer_id' => $customer->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_SHIPPING,
        ]);

        // 7. Make request to lookup endpoint (either route style works, testing RESTful here)
        $response = $this->getJson('/api/lookup/customer/P1234567');

        $response->assertOk();

        // 8. Assert response structure and presence of expenses
        $response->assertJsonPath('data.name', 'Zaki Customer')
            ->assertJsonPath('data.passport_no', 'P1234567')
            ->assertJsonPath('data.orders.0.order_number', 'ORD-ZAKI-99')
            ->assertJsonPath('data.orders.0.car.brand', 'Hyundai')
            ->assertJsonPath('data.orders.0.car.expenses.0.expense_type', 'Repairs')
            ->assertJsonCount(2, 'data.orders.0.car.expenses');

        // Test the query string route as well
        $responseQuery = $this->getJson('/api/lookup/customer?passport_no=P1234567');
        $responseQuery->assertOk()
            ->assertJsonPath('data.orders.0.car.expenses.1.expense_type', 'Customs')
            ->assertJsonPath('data.orders.0.car.expenses.1.local_amount', 1750);
    }
}
