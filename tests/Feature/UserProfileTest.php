<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::where('email', 'superadmin@zaki.com')->firstOrFail();
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/auth/update-profile', [
            'name' => 'New Name',
            'email' => 'newemail@zaki.com',
            'phone' => '0500000000',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'تم تحديث بيانات الملف الشخصي بنجاح.')
            ->assertJsonPath('user.name', 'New Name')
            ->assertJsonPath('user.email', 'newemail@zaki.com')
            ->assertJsonPath('user.phone', '0500000000');

        $this->user->refresh();
        $this->assertEquals('New Name', $this->user->name);
        $this->assertEquals('newemail@zaki.com', $this->user->email);
        $this->assertEquals('0500000000', $this->user->phone);
    }

    public function test_unauthenticated_user_cannot_update_profile(): void
    {
        $response = $this->putJson('/api/auth/update-profile', [
            'name' => 'New Name',
        ]);

        $response->assertUnauthorized();
    }

    public function test_cannot_update_email_to_existing_user_email(): void
    {
        $anotherUser = User::create([
            'name' => 'Another User',
            'email' => 'another@zaki.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/auth/update-profile', [
            'email' => 'another@zaki.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_update_profile_without_changing_email(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/auth/update-profile', [
            'name' => 'Only Name Changed',
            'email' => $this->user->email, // Same email should be ignored in unique validation
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Only Name Changed')
            ->assertJsonPath('user.email', $this->user->email);
    }

    public function test_authenticated_user_can_update_password(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/auth/update-password', [
            'current_password' => '12345678',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'تم تحديث كلمة المرور بنجاح.');

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));
    }

    public function test_authenticated_user_can_update_password_with_frontend_field_names(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/auth/update-password', [
            'currentPassword' => '12345678',
            'newPassword' => 'newpassword123',
            'confirmPassword' => 'newpassword123',
        ]);

        $response->assertOk();

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));
    }

    public function test_cannot_update_password_with_incorrect_current_password(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/auth/update-password', [
            'current_password' => 'wrong_password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_cannot_update_password_without_confirmation(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/auth/update-password', [
            'current_password' => '12345678',
            'password' => 'newpassword123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_authenticated_user_can_update_profile_using_post_method(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/auth/update-profile', [
            'name' => 'Name Via Post',
            'email' => 'postemail@zaki.com',
            'phone' => '0511111111',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Name Via Post')
            ->assertJsonPath('user.email', 'postemail@zaki.com')
            ->assertJsonPath('user.phone', '0511111111');

        $this->user->refresh();
        $this->assertEquals('Name Via Post', $this->user->name);
    }

    public function test_updating_user_profile_syncs_to_linked_agent_profile(): void
    {
        // Link an agent profile to this user
        $agent = \App\Models\Agent::create([
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/auth/update-profile', [
            'name' => 'Agent New Name',
            'email' => 'agentnew@zaki.com',
            'phone' => '0522222222',
        ]);

        $response->assertOk();

        $agent->refresh();
        $this->assertEquals('Agent New Name', $agent->name);
        $this->assertEquals('agentnew@zaki.com', $agent->email);
        $this->assertEquals('0522222222', $agent->phone);
    }
}
