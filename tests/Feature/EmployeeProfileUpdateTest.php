<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_profile(): void
    {
        $response = $this->put(route('attendance.profile.update'), [
            'phone' => '081234567890',
        ]);

        $response->assertRedirect('/');
    }

    public function test_employee_can_update_phone_number(): void
    {
        $user = User::factory()->create([
            'phone' => '0811111111',
            'password_changed_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('attendance.profile.update'), [
            'phone' => '0899999999',
        ]);

        $response->assertRedirect(route('attendance.profile'));
        $response->assertSessionHas('success');

        $this->assertEquals('0899999999', $user->fresh()->phone);
    }

    public function test_employee_can_change_password_without_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
            'password_changed_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('attendance.profile.update'), [
            'password' => 'new-secret-password-456',
            'password_confirmation' => 'new-secret-password-456',
        ]);

        $response->assertRedirect(route('attendance.profile'));
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-secret-password-456', $user->fresh()->password));
    }

    public function test_employee_can_update_profile_without_password(): void
    {
        $user = User::factory()->create([
            'phone' => '0811111111',
            'password' => Hash::make('original-password-123'),
            'password_changed_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('attendance.profile.update'), [
            'phone' => '0822222222',
        ]);

        $response->assertRedirect(route('attendance.profile'));
        $response->assertSessionHas('success');

        $this->assertEquals('0822222222', $user->fresh()->phone);
        $this->assertTrue(Hash::check('original-password-123', $user->fresh()->password));
    }

    public function test_employee_can_upload_avatar(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        $user = User::factory()->create([
            'password_changed_at' => now(),
        ]);

        $file = UploadedFile::fake()->image('my-avatar.jpg', 200, 200);

        $response = $this->actingAs($user)->put(route('attendance.profile.update'), [
            'avatar' => $file,
        ]);

        $response->assertRedirect(route('attendance.profile'));
        $response->assertSessionHas('success');

        $updatedUser = $user->fresh();
        $this->assertNotNull($updatedUser->avatar);
        Storage::disk('public')->assertExists($updatedUser->avatar);
    }
}
