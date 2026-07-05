<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerSystemAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_customer_without_user_or_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Public Customer',
            'email' => 'public-customer@zaki.com',
            'phone' => '0550000111',
            'passport_no' => 'AB123456',
        ]);

        $response
            ->assertCreated()
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user');

        $this->assertDatabaseHas('customers', [
            'email' => 'public-customer@zaki.com',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'public-customer@zaki.com',
        ]);
    }

    public function test_legacy_customer_user_cannot_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::create(['name' => 'customer', 'guard_name' => 'api']);

        $user = User::create([
            'name' => 'Legacy Customer',
            'email' => 'legacy-customer@zaki.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->assignRole('customer');

        $this->postJson('/api/auth/login', [
            'email' => 'legacy-customer@zaki.com',
            'password' => 'password123',
        ])->assertUnprocessable();
    }
}
