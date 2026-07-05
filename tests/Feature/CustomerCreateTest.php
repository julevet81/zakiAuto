<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_be_created_with_agent_id_without_user_account(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::where('email', 'superadmin@zaki.com')->firstOrFail();
        Sanctum::actingAs($user);

        Agent::create(['name' => 'Agent One']);
        Agent::create(['name' => 'Agent Two']);

        $response = $this->postJson('/api/customers', [
            'name' => 'مخمد عمر',
            'phone' => '0547896541',
            'email' => 'omar@zaki.com',
            'national_id' => '2514789632541785',
            'agent_id' => 2,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('customers', [
            'name' => 'مخمد عمر',
            'phone' => '0547896541',
            'email' => 'omar@zaki.com',
            'national_id' => '2514789632541785',
            'agent_id' => 2,
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'omar@zaki.com',
            'phone' => '0547896541',
        ]);
    }
}
