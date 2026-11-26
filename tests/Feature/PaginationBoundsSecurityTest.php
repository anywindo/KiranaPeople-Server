<?php

use App\Models\Location;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
});

it('clamps per_page on employee attendance history api endpoint', function () {
    $location = Location::factory()->create();
    $employee = User::factory()->create(['location_id' => $location->id]);
    $employee->assignRole('Employee');

    $this->actingAs($employee, 'sanctum')
        ->getJson('/api/v1/employee/attendance/history?per_page=999999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);

    $this->actingAs($employee, 'sanctum')
        ->getJson('/api/v1/employee/attendance/history?per_page=0')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);
});

it('clamps per_page on admin correction requests api endpoint', function () {
    $location = Location::factory()->create();
    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $this->actingAs($hr, 'sanctum')
        ->getJson('/api/v1/admin/correction-requests?per_page=999999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);

    $this->actingAs($hr, 'sanctum')
        ->getJson('/api/v1/admin/correction-requests?per_page=-5')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);
});

it('clamps per_page on admin web paginated endpoints', function (string $routeName, string $viewVar) {
    $location = Location::factory()->create();
    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $responseMax = $this->actingAs($hr)->get(route($routeName, ['per_page' => 999999]));
    $responseMax->assertOk();
    expect($responseMax->viewData($viewVar)->perPage())->toBe(100);

    $responseMin = $this->actingAs($hr)->get(route($routeName, ['per_page' => 0]));
    $responseMin->assertOk();
    expect($responseMin->viewData($viewVar)->perPage())->toBe(1);
})->with([
    'employees' => ['admin.employees.index', 'employees'],
    'absences' => ['admin.absences.index', 'absentees'],
    'attendances' => ['admin.attendances.index', 'attendances'],
    'breaks' => ['admin.breaks.index', 'attendances'],
]);
