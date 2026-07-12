<?php

namespace Tests\Feature;

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
}
