<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Car;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAvailableCarsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_available_cars_without_purchase_price(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier A']);
        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 1,
        ]);

        $availableCar = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'finition' => 'Hybrid',
            'manufacture_year' => 2024,
            'color' => 'White',
            'vin' => 'AVAILABLE-VIN-001',
            'foreign_purchase_price' => 12000,
            'shipping_cost' => 800,
            'sale_price' => 16000,
            'tracking_number' => 'TRK-AVAILABLE',
            'status' => Car::STATUS_AVAILABLE,
        ]);

        Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Honda',
            'model' => 'Civic',
            'manufacture_year' => 2023,
            'foreign_purchase_price' => 14000,
            'sale_price' => 19000,
            'status' => Car::STATUS_SOLD,
        ]);

        $response = $this->getJson('/api/cars/available');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $availableCar->id)
            ->assertJsonPath('data.0.brand', 'Toyota')
            ->assertJsonPath('data.0.model', 'Corolla')
            ->assertJsonPath('data.0.finition', 'Hybrid')
            ->assertJsonPath('data.0.sale_price', 16000)
            ->assertJsonMissingPath('data.0.foreign_purchase_price')
            ->assertJsonMissingPath('data.0.shipping_cost')
            ->assertJsonMissingPath('data.0.total_cost_local')
            ->assertJsonMissingPath('data.0.profit');
    }
}
