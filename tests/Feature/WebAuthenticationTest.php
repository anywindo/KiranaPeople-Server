<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
    }

    public function test_login_page_is_at_root()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_unauthenticated_users_are_redirected_to_root()
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/');

        $response = $this->get('/attendance');
        $response->assertRedirect('/');
    }

    public function test_hr_user_redirected_to_role_selection_or_dashboard_after_login()
    {
        $hr = User::factory()->create([
            'password' => bcrypt('password'),
        ]);
        $hr->assignRole('HR');

        $response = $this->post('/login', [
            'email' => $hr->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($hr);
    }

    public function test_employee_redirected_to_app_after_login()
    {
        $employee = User::factory()->create([
            'password' => bcrypt('password'),
        ]);
        $employee->assignRole('Employee');

        $response = $this->post('/login', [
            'email' => $employee->email,
            'password' => 'password',
        ]);

        // Employee akan otomatis diarahkan ke /attendance berdasarkan AuthController
        $response->assertRedirect('/attendance');
        $this->assertAuthenticatedAs($employee);
    }

    public function test_authenticated_hr_can_access_employee_routes()
    {
        // HR diarahkan langsung ke admin dashboard saat membuka attendance
        $hr = User::factory()->create();
        $hr->assignRole('HR');

        $response = $this->actingAs($hr)->get('/attendance');
        $response->assertRedirect('/admin/dashboard');

        $dashboardResponse = $this->actingAs($hr)->get('/admin/dashboard');
        $dashboardResponse->assertStatus(200);
    }

    public function test_authenticated_employee_cannot_access_hr_routes()
    {
        $employee = User::factory()->create();
        $employee->assignRole('Employee');

        $response = $this->actingAs($employee)->get('/admin/dashboard');
        $response->assertStatus(403);
    }
}
