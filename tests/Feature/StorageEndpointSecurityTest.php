<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageEndpointSecurityTest extends TestCase
{
    public function test_cannot_access_arbitrary_php_source_files(): void
    {
        $response = $this->get('/storage/index.php');

        $response->assertStatus(404);
    }

    public function test_cannot_access_unauthorized_extensions(): void
    {
        $disallowed = [
            'robots.txt',
            'composer.json',
            '.env',
            'database.sql',
            'script.sh',
        ];

        foreach ($disallowed as $path) {
            $response = $this->get('/storage/'.$path);
            $response->assertStatus(404);
        }
    }

    public function test_cannot_serve_svg_files_to_prevent_stored_xss(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/xss.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $response = $this->get('/storage/avatars/xss.svg');

        $response->assertStatus(404);
    }

    public function test_cannot_perform_directory_traversal(): void
    {
        $traversalAttempts = [
            '../index.php',
            'avatars/../../index.php',
            'selfies/..%2f..%2f.env',
            'attendance-selfies/../secret.jpg',
        ];

        foreach ($traversalAttempts as $attempt) {
            $response = $this->get('/storage/'.$attempt);
            $response->assertStatus(404);
        }
    }

    public function test_can_serve_valid_media_file_from_storage_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/test-avatar.webp', 'fake-webp-binary-data');

        $response = $this->get('/storage/avatars/test-avatar.webp');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/webp');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertEquals('fake-webp-binary-data', $response->getContent());
    }

    public function test_can_serve_valid_media_file_from_r2_disk(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        Storage::disk('r2')->put('attendance-selfies/checkin.jpg', 'fake-jpg-binary-data');

        $response = $this->get('/storage/attendance-selfies/checkin.jpg');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertEquals('fake-jpg-binary-data', $response->getContent());
    }
}
