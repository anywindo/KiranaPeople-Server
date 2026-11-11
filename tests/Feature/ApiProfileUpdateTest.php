<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('r2');
    }

    public function test_authenticated_user_can_update_profile_phone_via_put()
    {
        $user = User::factory()->create([
            'phone' => '08123456789',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/profile', [
            'phone' => '08987654321',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.phone', '08987654321')
            ->assertJsonPath('meta.success', true);

        $this->assertEquals('08987654321', $user->fresh()->phone);
    }

    public function test_authenticated_user_can_update_profile_avatar_via_post_with_method_spoofing()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('my_avatar.png', 400, 400);

        $response = $this->post('/api/v1/auth/profile', [
            '_method' => 'PUT',
            'avatar' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('meta.success', true);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->avatar);
    }

    public function test_authenticated_user_can_change_password_via_api()
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
            'password_changed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewSecurePass2026!',
            'password_confirmation' => 'NewSecurePass2026!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('meta.success', true);

        $this->assertTrue(Hash::check('NewSecurePass2026!', $user->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
            'password_changed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'WrongPassword!',
            'password' => 'NewSecurePass2026!',
            'password_confirmation' => 'NewSecurePass2026!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('meta.success', false);
    }
}
