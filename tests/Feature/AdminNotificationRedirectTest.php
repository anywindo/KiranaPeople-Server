<?php

use App\Models\Location;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
});

it('allows safe relative redirect_to on admin notification read', function () {
    $location = Location::factory()->create();
    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $response = $this->actingAs($hr)->get(route('admin.notifications.read', [
        'id' => 'non-existent-id',
        'redirect_to' => '/admin/dashboard',
    ]));

    $response->assertRedirect('/admin/dashboard');
});

it('allows same-origin redirect_to on admin notification read', function () {
    $location = Location::factory()->create();
    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $sameOriginUrl = url('/admin/employees');

    $response = $this->actingAs($hr)->get(route('admin.notifications.read', [
        'id' => 'non-existent-id',
        'redirect_to' => $sameOriginUrl,
    ]));

    $response->assertRedirect($sameOriginUrl);
});

it('blocks external and protocol-relative open redirects on admin notification read', function (string $maliciousUrl) {
    $location = Location::factory()->create();
    $hr = User::factory()->create(['location_id' => $location->id]);
    $hr->assignRole('HR');

    $response = $this->actingAs($hr)->get(route('admin.notifications.read', [
        'id' => 'non-existent-id',
        'redirect_to' => $maliciousUrl,
    ]));

    $response->assertRedirect(route('admin.attendances.index'));
})->with([
    'external https' => ['https://evil.example/phish'],
    'external http' => ['http://evil.example/phish'],
    'protocol-relative' => ['//evil.example/phish'],
    'backslash-prefixed' => ['/\\evil.example/phish'],
    'javascript scheme' => ['javascript:alert(1)'],
]);
