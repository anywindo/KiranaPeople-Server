<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);

        $location = Location::create([
            'name' => 'Kantor Pusat',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius' => 100,
        ]);

        $this->user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => Hash::make('OldPassword123!'),
            'dob' => '2000-01-15',
            'location_id' => $location->id,
        ]);
        $this->user->assignRole('Employee');
    }

    public function test_web_forgot_password_page_is_accessible(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertViewIs('auth.forgot-password');
        $response->assertSee('Lupa Kata Sandi?');
    }

    public function test_web_forgot_password_sends_notification_and_stores_tokens(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'employee@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Notification::assertSentTo(
            $this->user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) {
                return ! empty($notification->token) && strlen($notification->otp) === 6;
            }
        );

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'employee@example.com',
        ]);
    }

    public function test_web_reset_password_page_is_accessible(): void
    {
        $response = $this->get('/reset-password/sample-token?email=employee@example.com');

        $response->assertStatus(200);
        $response->assertViewIs('auth.reset-password');
        $response->assertSee('Atur Ulang Kata Sandi');
    }

    public function test_web_user_can_reset_password_with_valid_token(): void
    {
        $token = Str::random(60);
        $otp = '123456';

        DB::table('password_reset_tokens')->insert([
            'email' => 'employee@example.com',
            'token' => Hash::make($token),
            'otp' => Hash::make($otp),
            'created_at' => now(),
        ]);

        $response = $this->post('/reset-password', [
            'email' => 'employee@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewPassword123!', $this->user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'employee@example.com',
        ]);
    }

    public function test_web_reset_password_rejects_expired_token(): void
    {
        $token = Str::random(60);

        DB::table('password_reset_tokens')->insert([
            'email' => 'employee@example.com',
            'token' => Hash::make($token),
            'otp' => Hash::make('123456'),
            'created_at' => now()->subMinutes(16),
        ]);

        $response = $this->post('/reset-password', [
            'email' => 'employee@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('NewPassword123!', $this->user->fresh()->password));
    }

    public function test_api_forgot_password_sends_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'employee@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('meta.success', true);

        Notification::assertSentTo(
            $this->user,
            ResetPasswordNotification::class
        );
    }

    public function test_api_reset_password_with_valid_otp(): void
    {
        $token = Str::random(60);
        $otp = '654321';

        DB::table('password_reset_tokens')->insert([
            'email' => 'employee@example.com',
            'token' => Hash::make($token),
            'otp' => Hash::make($otp),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'employee@example.com',
            'otp' => $otp,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('meta.success', true);

        $this->assertTrue(Hash::check('NewPassword123!', $this->user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'employee@example.com',
        ]);
    }

    public function test_api_reset_password_rejects_invalid_otp(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'employee@example.com',
            'token' => Hash::make(Str::random(60)),
            'otp' => Hash::make('654321'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'employee@example.com',
            'otp' => '000000',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422);
        $this->assertFalse(Hash::check('NewPassword123!', $this->user->fresh()->password));
    }

    public function test_api_reset_password_rejects_expired_otp(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'employee@example.com',
            'token' => Hash::make(Str::random(60)),
            'otp' => Hash::make('654321'),
            'created_at' => now()->subMinutes(16),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'employee@example.com',
            'otp' => '654321',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422);
        $this->assertFalse(Hash::check('NewPassword123!', $this->user->fresh()->password));
    }

    public function test_forgot_password_rejects_unregistered_email(): void
    {
        Notification::fake();

        // Web submission with non-existent email
        $webResponse = $this->post('/forgot-password', [
            'email' => 'nobody@example.com',
        ]);
        $webResponse->assertRedirect();
        $webResponse->assertSessionHasErrors(['email']);

        // API submission with non-existent email
        $apiResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);
        $apiResponse->assertStatus(404);
        $apiResponse->assertJsonPath('meta.success', false);
        $apiResponse->assertJsonPath('meta.message', 'Email tidak terdaftar dalam sistem.');

        Notification::assertNothingSent();
    }

    public function test_api_verify_otp_with_valid_and_invalid_otp(): void
    {
        $token = Str::random(60);
        $otp = '789123';

        DB::table('password_reset_tokens')->insert([
            'email' => 'employee@example.com',
            'token' => Hash::make($token),
            'otp' => Hash::make($otp),
            'created_at' => now(),
        ]);

        // Wrong OTP
        $wrongResponse = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'employee@example.com',
            'otp' => '000000',
        ]);
        $wrongResponse->assertStatus(422);
        $wrongResponse->assertJsonPath('meta.success', false);

        // Correct OTP
        $validResponse = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'employee@example.com',
            'otp' => $otp,
        ]);
        $validResponse->assertStatus(200);
        $validResponse->assertJsonPath('meta.success', true);
    }
}
