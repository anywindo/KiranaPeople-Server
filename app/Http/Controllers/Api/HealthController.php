<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Perform a deep health check of core services (Database, Cache, Storage).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $checks = [];
        $isHealthy = true;
        $isDegraded = false;

        // 1. Database Check (Critical)
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);

            $checks['database'] = [
                'status' => 'ok',
                'latency_ms' => $dbLatency,
                'connection' => config('database.default'),
            ];
        } catch (\Throwable $e) {
            $isHealthy = false;
            $checks['database'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connection' => config('database.default'),
            ];
        }

        // 2. Cache Check
        $cacheStart = microtime(true);
        try {
            $testKey = 'health_check_'.uniqid();
            Cache::put($testKey, 'ok', 5);
            $cacheValue = Cache::get($testKey);
            Cache::forget($testKey);

            if ($cacheValue === 'ok') {
                $cacheLatency = round((microtime(true) - $cacheStart) * 1000, 2);
                $checks['cache'] = [
                    'status' => 'ok',
                    'latency_ms' => $cacheLatency,
                    'driver' => config('cache.default'),
                ];
            } else {
                $isDegraded = true;
                $checks['cache'] = [
                    'status' => 'degraded',
                    'message' => 'Cache write succeeded but retrieved value did not match.',
                    'driver' => config('cache.default'),
                ];
            }
        } catch (\Throwable $e) {
            $isDegraded = true;
            $checks['cache'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'driver' => config('cache.default'),
            ];
        }

        // 3. Local Storage Check
        try {
            $publicDisk = Storage::disk('public');
            $testFile = '.health_'.uniqid();
            $publicDisk->put($testFile, 'ok');
            $publicDisk->delete($testFile);

            $checks['storage']['local'] = [
                'status' => 'ok',
                'disk' => 'public',
            ];
        } catch (\Throwable $e) {
            $isDegraded = true;
            $checks['storage']['local'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // 4. Cloudflare R2 Storage Check (if configured)
        if (! empty(config('filesystems.disks.r2.bucket')) && ! empty(config('filesystems.disks.r2.key'))) {
            $r2Start = microtime(true);
            try {
                Storage::disk('r2')->exists('health.txt');
                $r2Latency = round((microtime(true) - $r2Start) * 1000, 2);
                $checks['storage']['r2'] = [
                    'status' => 'ok',
                    'latency_ms' => $r2Latency,
                    'bucket' => config('filesystems.disks.r2.bucket'),
                ];
            } catch (\Throwable $e) {
                $isDegraded = true;
                $checks['storage']['r2'] = [
                    'status' => 'degraded',
                    'message' => $e->getMessage(),
                    'bucket' => config('filesystems.disks.r2.bucket'),
                ];
            }
        }

        // Determine overall status and HTTP status code
        if (! $isHealthy) {
            $overallStatus = 'unhealthy';
            $statusCode = 503;
        } elseif ($isDegraded) {
            $overallStatus = 'degraded';
            $statusCode = 200;
        } else {
            $overallStatus = 'healthy';
            $statusCode = 200;
        }

        return response()->json([
            'status' => $overallStatus,
            'name' => config('app.name'),
            'version' => config('app.version', '1.0.1'),
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $statusCode);
    }
}
