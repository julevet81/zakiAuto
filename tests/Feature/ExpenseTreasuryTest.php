<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ServiceProviderModel;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTreasuryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ServiceProviderModel $serviceProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::where('email', 'superadmin@zaki.com')->firstOrFail();

        $this->serviceProvider = ServiceProviderModel::create([
            'name' => 'Logistics Provider',
            'phone' => '0500000000',
            'provider_type' => 'shipping',
        ]);

        // Seed an initial treasury balance
        $prev = 10000.00;
        TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => $prev,
            'previous_balence' => 0.00,
            'current_balence' => $prev,
            'source_type' => 'manual_deposit',
            'source_id' => 0,
            'transaction_date' => now()->toDateString(),
            'status' => TreasuryTransaction::STATUS_APPROVED,
            'notes' => 'Opening balance',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_creating_expense_deducts_from_treasury(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/expenses', [
            'expense_type' => 'Office Rent',
            'amount' => 1500.00,
            'expense_date' => now()->toDateString(),
            'service_provider_id' => $this->serviceProvider->id,
            'notes' => 'Rent for HQ',
        ]);

        $response->assertCreated();

        $expense = Expense::where('expense_type', 'Office Rent')->first();
        $this->assertNotNull($expense);

        // Verify the treasury transaction was created
        $this->assertDatabaseHas('treasury_transactions', [
            'direction' => TreasuryTransaction::DIRECTION_OUT,
            'amount' => 1500.00,
            'previous_balence' => 10000.00,
            'current_balence' => 8500.00,
            'source_type' => TreasuryTransaction::SOURCE_EXPENSE,
            'source_id' => $expense->id,
            'transaction_date' => $expense->expense_date->format('Y-m-d') . ' 00:00:00',
        ]);
    }

    public function test_updating_expense_amount_creates_reversal_and_new_transaction(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Create expense
        $response = $this->postJson('/api/expenses', [
            'expense_type' => 'Office Rent',
            'amount' => 1500.00,
            'expense_date' => now()->toDateString(),
            'service_provider_id' => $this->serviceProvider->id,
            'notes' => 'Rent for HQ',
        ]);
        $response->assertCreated();
        $expense = Expense::where('expense_type', 'Office Rent')->first();

        // 2. Update expense amount to 2000.00
        $responseUpdate = $this->putJson("/api/expenses/{$expense->id}", [
            'expense_type' => 'Office Rent',
            'amount' => 2000.00,
            'expense_date' => now()->toDateString(),
            'service_provider_id' => $this->serviceProvider->id,
            'notes' => 'Rent for HQ updated',
        ]);
        $responseUpdate->assertOk();

        // Verify reversal of old amount
        $this->assertDatabaseHas('treasury_transactions', [
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => 1500.00,
            'previous_balence' => 8500.00,
            'current_balence' => 10000.00,
            'source_type' => TreasuryTransaction::SOURCE_EXPENSE,
            'source_id' => $expense->id,
            'transaction_date' => now()->toDateString() . ' 00:00:00',
            'notes' => 'إلغاء القيمة السابقة للمصروف المعدل رقم #' . $expense->id,
        ]);

        // Verify creation of new amount transaction
        $this->assertDatabaseHas('treasury_transactions', [
            'direction' => TreasuryTransaction::DIRECTION_OUT,
            'amount' => 2000.00,
            'previous_balence' => 10000.00,
            'current_balence' => 8000.00,
            'source_type' => TreasuryTransaction::SOURCE_EXPENSE,
            'source_id' => $expense->id,
            'transaction_date' => now()->toDateString() . ' 00:00:00',
        ]);
    }

    public function test_deleting_expense_creates_reversal(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Create expense
        $response = $this->postJson('/api/expenses', [
            'expense_type' => 'Office Rent',
            'amount' => 1500.00,
            'expense_date' => now()->toDateString(),
            'service_provider_id' => $this->serviceProvider->id,
            'notes' => 'Rent for HQ',
        ]);
        $response->assertCreated();
        $expense = Expense::where('expense_type', 'Office Rent')->first();

        // 2. Delete expense
        $responseDelete = $this->deleteJson("/api/expenses/{$expense->id}");
        $responseDelete->assertOk();

        // Verify reversal transaction
        $this->assertDatabaseHas('treasury_transactions', [
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => 1500.00,
            'previous_balence' => 8500.00,
            'current_balence' => 10000.00,
            'source_type' => TreasuryTransaction::SOURCE_EXPENSE,
            'source_id' => $expense->id,
            'transaction_date' => now()->toDateString() . ' 00:00:00',
            'notes' => 'إلغاء مصروف محذوف رقم #' . $expense->id,
        ]);

        $this->assertDatabaseMissing('expenses', [
            'id' => $expense->id,
        ]);
    }
}
