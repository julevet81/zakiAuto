<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Car;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierPaymentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::where('email', 'superadmin@zaki.com')->firstOrFail();

        $this->supplier = Supplier::create([
            'name' => 'Test Supplier',
            'phone' => '0555555555',
            'email' => 'supplier@test.com',
        ]);

        $this->batch = Batch::create([
            'supplier_id' => $this->supplier->id,
            'exchange_rate' => 3.67,
            'status' => Batch::STATUS_PARTIAL,
        ]);

        // Add a car to the batch so it has total_cost_foreign > 0
        $car = Car::create([
            'batch_id' => $this->batch->id,
            'supplier_id' => $this->supplier->id,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'manufacture_year' => '2021',
            'foreign_purchase_price' => 1000,
            'sale_price' => 12000,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        $this->batch->recomputeTotalCostForeign();
    }

    public function test_store_supplier_payment_within_due_amount_succeeds(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/supplier-payments', [
            'supplier_id' => $this->supplier->id,
            'amount_foreign' => 800,
            'exchange_rate' => 3.67,
            'payment_date' => now()->toDateString(),
            'notes' => 'Valid payment',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('supplier_payments', [
            'supplier_id' => $this->supplier->id,
            'amount_foreign' => 800,
        ]);
    }

    public function test_store_supplier_payment_exceeding_due_amount_fails(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/supplier-payments', [
            'supplier_id' => $this->supplier->id,
            'amount_foreign' => 1200, // Exceeds the batch's total_cost_foreign of 1000
            'exchange_rate' => 3.67,
            'payment_date' => now()->toDateString(),
            'notes' => 'Overpayment',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'المبلغ المدفوع أكبر من إجمالي المستحق للمورد. إجمالي المستحق الحالي: 1000',
        ]);
    }

    public function test_update_supplier_payment_within_due_amount_succeeds(): void
    {
        Sanctum::actingAs($this->user);

        // Create an initial payment of 500
        $payment = SupplierPayment::create([
            'batch_id' => $this->batch->id,
            'supplier_id' => $this->supplier->id,
            'amount_foreign' => 500,
            'exchange_rate' => 3.67,
            'amount_local' => 500 * 3.67,
            'payment_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // Update it to 800 (remaining batch needs is 1000, so 800 is allowed)
        $response = $this->putJson("/api/supplier-payments/{$payment->id}", [
            'amount_foreign' => 800,
        ]);

        $response->assertOk();
        $this->assertEquals(800, $payment->refresh()->amount_foreign);
    }

    public function test_update_supplier_payment_exceeding_due_amount_fails(): void
    {
        Sanctum::actingAs($this->user);

        // Create an initial payment of 500
        $payment = SupplierPayment::create([
            'batch_id' => $this->batch->id,
            'supplier_id' => $this->supplier->id,
            'amount_foreign' => 500,
            'exchange_rate' => 3.67,
            'amount_local' => 500 * 3.67,
            'payment_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // Update it to 1200 (remaining batch needs is 1000, so 1200 exceeds it)
        $response = $this->putJson("/api/supplier-payments/{$payment->id}", [
            'amount_foreign' => 1200,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'المبلغ المدفوع أكبر من إجمالي المستحق لهذه الدفعة. إجمالي المستحق الحالي: 1000',
        ]);
    }
}
