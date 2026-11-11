<?php

// We are invoked via api/index.php, so we are running in Vercel Serverless environment
putenv('VERCEL=1');
$_ENV['VERCEL'] = '1';
$_SERVER['VERCEL'] = '1';

// Prepare writable storage directory structure in /tmp for Vercel Serverless
$directories = [
    '/tmp/storage',
    '/tmp/storage/app',
    '/tmp/storage/app/public',
    '/tmp/storage/app/public/selfies',
    '/tmp/storage/app/public/avatars',
    '/tmp/storage/app/public/attendance-selfies',
    '/tmp/storage/framework',
    '/tmp/storage/framework/cache',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
];

foreach ($directories as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }
}

// Fix: Vercel serverless functions strip `/api` from REQUEST_URI
if (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    if (str_starts_with($uri, '/v1') || str_starts_with($uri, '/health') || $uri === '' || $uri === '/') {
        if ($uri !== '' && $uri !== '/') {
            $_SERVER['REQUEST_URI'] = '/api'.$uri;
        }
    }
}

try {
    require __DIR__.'/../public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString()),
    ]);
}
