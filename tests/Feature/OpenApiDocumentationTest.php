<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('HR');

        $this->employee = User::factory()->create();
        $this->employee->assignRole('Employee');
    }

    public function test_unauthenticated_guest_cannot_access_openapi_in_non_local_environment(): void
    {
        $this->get('/docs/api')->assertStatus(403);
        $this->get('/docs/api.json')->assertStatus(403);
    }

    public function test_regular_employee_cannot_access_openapi_in_non_local_environment(): void
    {
        $this->actingAs($this->employee)
            ->get('/docs/api')
            ->assertStatus(403);

        $this->actingAs($this->employee)
            ->get('/docs/api.json')
            ->assertStatus(403);
    }

    public function test_admin_can_access_openapi_ui(): void
    {
        $response = $this->actingAs($this->admin)->get('/docs/api');

        $response->assertStatus(200);
        $response->assertSee('Kirana People API Documentation');
    }

    public function test_admin_can_access_openapi_json_specification(): void
    {
        $response = $this->actingAs($this->admin)->get('/docs/api.json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'openapi',
            'info' => [
                'title',
                'version',
                'description',
            ],
            'paths',
        ]);
        $response->assertJsonPath('info.title', 'Kirana People API Documentation');
    }

    public function test_guest_can_access_openapi_in_local_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->get('/docs/api')->assertStatus(200);
        $this->get('/docs/api.json')->assertStatus(200);
    }

    public function test_api_docs_can_be_disabled_via_config_even_for_admin(): void
    {
        config(['api.docs_enabled' => false]);

        $this->actingAs($this->admin)->get('/docs/api')->assertStatus(403);
        $this->actingAs($this->admin)->get('/docs/api.json')->assertStatus(403);
        $this->get('/docs/api')->assertStatus(403);
    }
}
