<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExceptionHandlingSecurityTest extends TestCase
{
    public function test_production_environment_masks_raw_exception_messages(): void
    {
        // Register a temporary route throwing a sensitive exception (e.g. database error)
        Route::get('/api/test-exception-leak', function () {
            throw new \RuntimeException('SQLSTATE[42S02]: Table terriblenight.secrets not found (select * from secrets)');
        });

        // Test in production environment
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->getJson('/api/test-exception-leak');

        $response->assertStatus(500);
        $response->assertJson([
            'meta' => [
                'success' => false,
                'message' => 'Internal server error',
            ],
        ]);

        // Ensure sensitive internal trace / SQL is not in response
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('terriblenight.secrets', $response->getContent());
    }

    public function test_non_production_environment_preserves_debug_message(): void
    {
        Route::get('/api/test-exception-debug', function () {
            throw new \RuntimeException('Debug error for local development');
        });

        $this->app->detectEnvironment(fn () => 'local');

        $response = $this->getJson('/api/test-exception-debug');

        $response->assertStatus(500);
        $response->assertJson([
            'meta' => [
                'success' => false,
                'message' => 'Debug error for local development',
            ],
        ]);
    }
}
