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

class ProfileRemainingBalancesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::where('email', 'superadmin@zaki.com')->firstOrFail();
        Sanctum::actingAs($this->user);
    }

    public function test_supplier_profile_returns_remaining_amount_owed_to_supplier(): void
    {
        $supplier = Supplier::create(['name' => 'Tokyo Auto Export']);

        Batch::create([
            'supplier_id' => $supplier->id,
            'total_cost_foreign' => 1000,
            'total_paid_amount_foreign' => 250,
            'exchange_rate' => 135,
            'status' => Batch::STATUS_PARTIAL,
        ]);

        Batch::create([
            'supplier_id' => $supplier->id,
            'total_cost_foreign' => 400,
            'total_paid_amount_foreign' => 400,
            'exchange_rate' => 135,
            'status' => Batch::STATUS_FULLY_PAID,
        ]);

        $response = $this->getJson("/api/suppliers/{$supplier->id}");

        $response->assertOk();
        $this->assertEquals(750.0, (float) $response->json('data.total_remaining'));
    }

    public function test_customer_profile_returns_remaining_amount_owed_by_customer(): void
    {
        $supplier = Supplier::create(['name' => 'Tokyo Auto Export']);
        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'total_cost_foreign' => 2000,
            'total_paid_amount_foreign' => 0,
            'exchange_rate' => 135,
            'status' => Batch::STATUS_PARTIAL,
        ]);
        $customer = Customer::create(['name' => 'Ahmad Zaki']);

        $carOne = $this->createCar($batch, $supplier, 'VINBALANCE001', 1000);
        $carTwo = $this->createCar($batch, $supplier, 'VINBALANCE002', 1200);

        Order::create([
            'order_number' => 'ORD-BAL-001',
            'customer_id' => $customer->id,
            'car_id' => $carOne->id,
            'status' => Order::STATUS_SHIPPING,
            'paid_amount' => 200,
            'remaining_amount' => 800,
            'created_by' => $this->user->id,
        ]);

        Order::create([
            'order_number' => 'ORD-BAL-002',
            'customer_id' => $customer->id,
            'car_id' => $carTwo->id,
            'status' => Order::STATUS_SHIPPING,
            'paid_amount' => 300,
            'remaining_amount' => 900,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/customers/{$customer->id}");

        $response->assertOk();
        $this->assertEquals(1700.0, (float) $response->json('data.total_remaining'));
    }

    private function createCar(Batch $batch, Supplier $supplier, string $vin, float $salePrice): Car
    {
        return Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Toyota',
            'model' => 'Camry',
            'manufacture_year' => '2024',
            'vin' => $vin,
            'foreign_purchase_price' => 700,
            'sale_price' => $salePrice,
            'status' => Car::STATUS_AVAILABLE,
        ]);
    }
}
