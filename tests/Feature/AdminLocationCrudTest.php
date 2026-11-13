<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminLocationCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('HR');
    }

    public function test_admin_can_view_location_index()
    {
        $location = Location::create([
            'name' => 'Kantor Batang',
            'latitude' => -6.912345,
            'longitude' => 109.712345,
            'radius' => 75,
        ]);

        $response = $this->actingAs($this->admin, 'web')->get(route('admin.locations.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.locations.index');
        $response->assertViewHas('locations');
        $response->assertSee('Kantor Batang');
        $response->assertSee('75 m');
    }

    public function test_admin_can_view_create_location_page()
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.locations.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.locations.create');
        $response->assertSee('Peta Interaktif Geofence');
        $response->assertSee('radiusRange');
    }

    public function test_admin_can_store_new_location()
    {
        $response = $this->actingAs($this->admin, 'web')->post(route('admin.locations.store'), [
            'name' => 'Gudang Pusat Cikande',
            'latitude' => -6.211234,
            'longitude' => 106.345678,
            'radius' => 120,
        ]);

        $response->assertRedirect(route('admin.locations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('locations', [
            'name' => 'Gudang Pusat Cikande',
            'latitude' => -6.211234,
            'longitude' => 106.345678,
            'radius' => 120,
        ]);
    }

    public function test_admin_can_view_edit_location_page()
    {
        $location = Location::create([
            'name' => 'Pabrik Pekalongan',
            'latitude' => -6.881234,
            'longitude' => 109.671234,
            'radius' => 150,
        ]);

        $response = $this->actingAs($this->admin, 'web')->get(route('admin.locations.edit', $location));

        $response->assertStatus(200);
        $response->assertViewIs('admin.locations.edit');
        $response->assertSee('Pabrik Pekalongan');
        $response->assertSee('150');
    }

    public function test_admin_can_update_location()
    {
        $location = Location::create([
            'name' => 'Kantor Lama',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'radius' => 50,
        ]);

        $response = $this->actingAs($this->admin, 'web')->put(route('admin.locations.update', $location), [
            'name' => 'Kantor Baru Sudirman',
            'latitude' => -6.215000,
            'longitude' => 106.820000,
            'radius' => 80,
        ]);

        $response->assertRedirect(route('admin.locations.index'));
        $response->assertSessionHas('success');

        $location->refresh();
        $this->assertEquals('Kantor Baru Sudirman', $location->name);
        $this->assertEquals(80, $location->radius);
    }

    public function test_admin_cannot_delete_location_used_by_employees()
    {
        $location = Location::create([
            'name' => 'Kantor Aktif',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'radius' => 50,
        ]);

        User::factory()->create([
            'location_id' => $location->id,
        ]);

        $response = $this->actingAs($this->admin, 'web')->delete(route('admin.locations.destroy', $location));

        $response->assertRedirect(route('admin.locations.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }

    public function test_admin_can_delete_unused_location()
    {
        $location = Location::create([
            'name' => 'Kantor Kosong',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'radius' => 50,
        ]);

        $response = $this->actingAs($this->admin, 'web')->delete(route('admin.locations.destroy', $location));

        $response->assertRedirect(route('admin.locations.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }
}
