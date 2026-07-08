<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_agent_creates_user_first_and_links_agent_profile(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Sanctum::actingAs(User::where('email', 'superadmin@zaki.com')->firstOrFail());

        $response = $this->postJson('/api/agents', [
            'name' => 'Agent Smith',
            'email' => 'agent.smith@example.com',
            'phone' => '0555000111',
            'password' => 'password123',
            'address' => 'Tripoli',
            'notes' => 'Created from agent endpoint',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Agent Smith')
            ->assertJsonPath('data.email', 'agent.smith@example.com');

        $user = User::where('email', 'agent.smith@example.com')->firstOrFail();
        $agent = Agent::where('user_id', $user->id)->firstOrFail();

        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertTrue($user->hasRole('agent'));
        $this->assertSame($agent->id, $response->json('data.id'));
        $this->assertSame('Agent Smith', $agent->name);
        $this->assertSame('0555000111', $agent->phone);
    }
}
