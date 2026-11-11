<?php

use App\Models\Location;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
});

test('creating employee without location_id fails validation in web backend', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $response = $this->actingAs($admin)->post(route('admin.employees.store'), [
        'name' => 'Test Employee',
        'email' => 'test_emp_nolocation@example.com',
        'dob' => '2000-01-01',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'location_id' => null,
    ]);

    $response->assertSessionHasErrors('location_id');
});

test('updating employee without location_id fails validation in web backend', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $location = Location::firstOrCreate([
        'name' => 'Kantor Pusat',
    ], [
        'latitude' => -6.2,
        'longitude' => 106.8,
        'radius' => 50,
    ]);

    $employee = User::factory()->create(['location_id' => $location->id]);

    $response = $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
        'name' => 'Updated Name',
        'email' => $employee->email,
        'dob' => '2000-01-01',
        'location_id' => null,
    ]);

    $response->assertSessionHasErrors('location_id');
});
