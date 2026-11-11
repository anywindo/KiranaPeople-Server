<?php

use Illuminate\Support\Facades\DB;

it('performs deep health check and returns 200 when services are healthy', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'name',
            'version',
            'environment',
            'timestamp',
            'checks' => [
                'database' => [
                    'status',
                    'latency_ms',
                    'connection',
                ],
                'cache' => [
                    'status',
                    'latency_ms',
                    'driver',
                ],
                'storage' => [
                    'local' => [
                        'status',
                        'disk',
                    ],
                ],
            ],
        ]);

    expect($response->json('status'))->toBeIn(['healthy', 'degraded']);
    expect($response->json('checks.database.status'))->toBe('ok');
    expect($response->json('checks.cache.status'))->toBe('ok');
    expect($response->json('checks.storage.local.status'))->toBe('ok');
});

it('returns 503 and unhealthy status when database connection fails', function () {
    DB::shouldReceive('connection->getPdo')
        ->andThrow(new \Exception('Database connection timeout'));

    $response = $this->getJson('/api/health');

    $response->assertStatus(503)
        ->assertJson([
            'status' => 'unhealthy',
            'checks' => [
                'database' => [
                    'status' => 'error',
                ],
            ],
        ]);
});
