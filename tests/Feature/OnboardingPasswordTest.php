<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnboardingPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
    }

    public function test_user_with_default_password_is_redirected_to_onboarding_upon_login(): void
    {
        $dob = '2004-07-12';
        $defaultPassword = '12072004';

        $employee = User::factory()->needsPasswordChange()->create([
            'dob' => $dob,
            'password' => Hash::make($defaultPassword),
        ]);
        $employee->assignRole('Employee');

        $this->assertTrue($employee->mustChangePassword());

        $response = $this->post('/login', [
            'email' => $employee->email,
            'password' => $defaultPassword,
        ]);

        $response->assertRedirect(route('onboarding.password'));
        $this->assertAuthenticatedAs($employee);
    }

    public function test_user_with_default_password_is_redirected_when_accessing_protected_routes(): void
    {
        $employee = User::factory()->needsPasswordChange()->create();
        $employee->assignRole('Employee');

        $response = $this->actingAs($employee)->get('/attendance');
        $response->assertRedirect(route('onboarding.password'));

        $responseHistory = $this->actingAs($employee)->get('/attendance/history');
        $responseHistory->assertRedirect(route('onboarding.password'));

        $responseProfile = $this->actingAs($employee)->get('/attendance/profile');
        $responseProfile->assertRedirect(route('onboarding.password'));
    }

    public function test_onboarding_page_displays_employee_info(): void
    {
        $employee = User::factory()->needsPasswordChange()->create([
            'name' => 'Arwindo Pratama',
            'employee_id' => 'EMP-00329',
        ]);
        $employee->assignRole('Employee');

        $response = $this->actingAs($employee)->get('/onboarding/password');

        $response->assertStatus(200);
        $response->assertViewIs('auth.onboarding-password');
        $response->assertSee('Arwindo Pratama');
        $response->assertSee('EMP-00329');
        $response->assertSee('KIRANA');
    }

    public function test_password_cannot_be_shorter_than_8_characters(): void
    {
        $employee = User::factory()->needsPasswordChange()->create();
        $employee->assignRole('Employee');

        $response = $this->actingAs($employee)->post('/onboarding/password', [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertNull($employee->fresh()->password_changed_at);
    }

    public function test_password_must_match_confirmation(): void
    {
        $employee = User::factory()->needsPasswordChange()->create();
        $employee->assignRole('Employee');

        $response = $this->actingAs($employee)->post('/onboarding/password', [
            'password' => 'newPassword123!',
            'password_confirmation' => 'mismatchPassword!',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertNull($employee->fresh()->password_changed_at);
    }

    public function test_password_cannot_be_the_default_birthday_or_password(): void
    {
        $dob = '1995-08-15';
        $defaultBirthday = '15081995';

        $employee = User::factory()->needsPasswordChange()->create([
            'dob' => $dob,
            'password' => Hash::make($defaultBirthday),
        ]);
        $employee->assignRole('Employee');

        // Cannot set to default birthday
        $response = $this->actingAs($employee)->post('/onboarding/password', [
            'password' => $defaultBirthday,
            'password_confirmation' => $defaultBirthday,
        ]);
        $response->assertSessionHasErrors(['password']);

        // Cannot set to generic 'password'
        $responseGeneric = $this->actingAs($employee)->post('/onboarding/password', [
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $responseGeneric->assertSessionHasErrors(['password']);

        $this->assertNull($employee->fresh()->password_changed_at);
    }

    public function test_password_requires_mixed_case_and_numbers(): void
    {
        $employee = User::factory()->needsPasswordChange()->create();
        $employee->assignRole('Employee');

        // Only lowercase with numbers (no uppercase)
        $resNoUpper = $this->actingAs($employee)->post('/onboarding/password', [
            'password' => 'secretpass123',
            'password_confirmation' => 'secretpass123',
        ]);
        $resNoUpper->assertSessionHasErrors(['password']);

        // Only letters (no numbers)
        $resNoNumber = $this->actingAs($employee)->post('/onboarding/password', [
            'password' => 'SecretPassword',
            'password_confirmation' => 'SecretPassword',
        ]);
        $resNoNumber->assertSessionHasErrors(['password']);

        $this->assertNull($employee->fresh()->password_changed_at);
    }

    public function test_user_can_successfully_update_password_and_access_app(): void
    {
        $dob = '2004-07-12';
        $defaultPassword = '12072004';
        $newPassword = 'SecurePassword2026!';

        $employee = User::factory()->needsPasswordChange()->create([
            'dob' => $dob,
            'password' => Hash::make($defaultPassword),
        ]);
        $employee->assignRole('Employee');

        $response = $this->actingAs($employee)->post('/onboarding/password', [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertRedirect('/attendance');

        $employee->refresh();
        $this->assertNotNull($employee->password_changed_at);
        $this->assertFalse($employee->mustChangePassword());
        $this->assertTrue(Hash::check($newPassword, $employee->password));

        // Now employee can access /attendance without redirect
        $accessResponse = $this->actingAs($employee)->get('/attendance');
        $accessResponse->assertStatus(200);
    }

    public function test_user_already_changed_password_cannot_access_onboarding(): void
    {
        $employee = User::factory()->create([
            'password_changed_at' => now(),
        ]);
        $employee->assignRole('Employee');

        $response = $this->actingAs($employee)->get('/onboarding/password');
        $response->assertRedirect('/attendance');
    }

    public function test_superadmin_is_exempt_from_mandatory_onboarding(): void
    {
        $superadmin = User::factory()->needsPasswordChange()->create();
        $superadmin->assignRole('super-admin');

        $this->assertFalse($superadmin->mustChangePassword());

        $response = $this->actingAs($superadmin)->get('/admin/dashboard');
        $response->assertStatus(200);
    }
}
