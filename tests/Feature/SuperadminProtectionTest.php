<?php

use App\Models\Location;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
});

it('prevents superadmin deletion at model level', function () {
    $location = Location::factory()->create();
    $superadmin = User::factory()->create(['location_id' => $location->id]);
    $superadmin->assignRole('super-admin');

    expect(fn () => $superadmin->delete())
        ->toThrow(DomainException::class, 'Akun Superadmin tidak dapat dihapus.');

    expect(User::where('id', $superadmin->id)->exists())->toBeTrue();
});

it('prevents superadmin deletion via web controller', function () {
    $location = Location::factory()->create();
    $superadmin = User::factory()->create(['location_id' => $location->id]);
    $superadmin->assignRole('super-admin');

    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $response = $this->actingAs($hr)->delete(route('admin.employees.destroy', $superadmin));

    $response->assertRedirect(route('admin.employees.index'))
        ->assertSessionHas('error', 'Akun Superadmin tidak dapat dihapus.');

    expect(User::where('id', $superadmin->id)->exists())->toBeTrue();
});

it('prevents superadmin deletion via api controller', function () {
    $location = Location::factory()->create();
    $superadmin = User::factory()->create(['location_id' => $location->id]);
    $superadmin->assignRole('super-admin');

    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $response = $this->actingAs($hr, 'sanctum')->deleteJson("/api/v1/admin/employees/{$superadmin->id}");

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'Akun Superadmin tidak dapat dihapus.',
        ]);

    expect(User::where('id', $superadmin->id)->exists())->toBeTrue();
});

it('prevents non-superadmin from editing superadmin', function () {
    $location = Location::factory()->create();
    $superadmin = User::factory()->create(['location_id' => $location->id]);
    $superadmin->assignRole('super-admin');

    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $editResponse = $this->actingAs($hr)->get(route('admin.employees.edit', $superadmin));
    $editResponse->assertRedirect(route('admin.employees.index'))
        ->assertSessionHas('error', 'Hanya Superadmin yang dapat mengubah akun Superadmin.');

    $updateResponse = $this->actingAs($hr)->put(route('admin.employees.update', $superadmin), [
        'name' => 'Hacked Name',
        'email' => 'hacked@example.com',
        'dob' => '1990-01-01',
        'location_id' => $location->id,
    ]);
    $updateResponse->assertRedirect(route('admin.employees.index'))
        ->assertSessionHas('error', 'Hanya Superadmin yang dapat mengubah akun Superadmin.');

    $superadmin->refresh();
    expect($superadmin->name)->not->toBe('Hacked Name');
});

it('hides delete button for superadmin in employee list view', function () {
    $location = Location::factory()->create();
    $superadmin = User::factory()->create([
        'name' => 'Super Admin Real',
        'email' => 'superadmin_real@example.com',
        'location_id' => $location->id,
    ]);
    $superadmin->assignRole('super-admin');

    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $response = $this->actingAs($hr)->get(route('admin.employees.index'));

    $response->assertOk();
    // Confirm delete form for superadmin is not rendered
    $deleteActionUrl = route('admin.employees.destroy', $superadmin);
    expect($response->getContent())->not->toContain('action="'.$deleteActionUrl.'"');
});

it('prevents non-superadmin from assigning super-admin role when creating an employee', function () {
    $location = Location::factory()->create();
    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $response = $this->actingAs($hr)->post(route('admin.employees.store'), [
        'name' => 'Escalated User',
        'email' => 'escalated@example.com',
        'dob' => '1995-05-05',
        'location_id' => $location->id,
        'role' => 'super-admin',
    ]);

    $response->assertSessionHasErrors('role');
    expect(User::where('email', 'escalated@example.com')->exists())->toBeFalse();
});

it('prevents non-superadmin from escalating an existing employee to super-admin role', function () {
    $location = Location::factory()->create();
    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $employee = User::factory()->create(['location_id' => $location->id]);
    $employee->assignRole('Employee');

    $response = $this->actingAs($hr)->put(route('admin.employees.update', $employee), [
        'name' => $employee->name,
        'email' => $employee->email,
        'dob' => '1995-05-05',
        'location_id' => $location->id,
        'role' => 'super-admin',
    ]);

    $response->assertSessionHasErrors('role');
    $employee->refresh();
    expect($employee->hasRole('super-admin'))->toBeFalse();
    expect($employee->hasRole('Employee'))->toBeTrue();
});

it('allows superadmin to assign super-admin role via web controller', function () {
    $location = Location::factory()->create();
    $superadmin = User::factory()->create(['location_id' => $location->id]);
    $superadmin->assignRole('super-admin');

    $employee = User::factory()->create(['location_id' => $location->id]);
    $employee->assignRole('Employee');

    $response = $this->actingAs($superadmin)->put(route('admin.employees.update', $employee), [
        'name' => $employee->name,
        'email' => $employee->email,
        'dob' => '1995-05-05',
        'location_id' => $location->id,
        'role' => 'super-admin',
    ]);

    $response->assertRedirect(route('admin.employees.index'))
        ->assertSessionHas('success');

    $employee->refresh();
    expect($employee->hasRole('super-admin'))->toBeTrue();
});
