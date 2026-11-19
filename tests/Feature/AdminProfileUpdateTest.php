<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'password' => Hash::make('admin-password-123'),
            'password_changed_at' => now(),
        ]);
        $this->admin->assignRole('HR');
    }

    public function test_admin_can_update_profile_info(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.profile.update'), [
            'name' => 'Updated Admin Name',
            'email' => 'updatedadmin@example.com',
            'dob' => '1990-05-15',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('Updated Admin Name', $this->admin->fresh()->name);
        $this->assertEquals('updatedadmin@example.com', $this->admin->fresh()->email);
    }

    public function test_admin_cannot_change_password_without_current_password(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.profile.update'), [
            'password' => 'new-admin-password-789',
            'password_confirmation' => 'new-admin-password-789',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue(Hash::check('admin-password-123', $this->admin->fresh()->password));
    }

    public function test_admin_cannot_change_password_with_incorrect_current_password(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.profile.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-admin-password-789',
            'password_confirmation' => 'new-admin-password-789',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue(Hash::check('admin-password-123', $this->admin->fresh()->password));
    }

    public function test_admin_can_change_password_with_valid_current_password(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.profile.update'), [
            'current_password' => 'admin-password-123',
            'password' => 'new-admin-password-789',
            'password_confirmation' => 'new-admin-password-789',
        ]);

        $response->assertSessionHas('success');
        $refreshed = $this->admin->fresh();
        $this->assertTrue(Hash::check('new-admin-password-789', $refreshed->password));
    }
}
