<?php

namespace Tests\Feature;

use App\Jobs\UploadFileToR2Job;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadFileToR2JobTest extends TestCase
{
    public function test_file_upload_service_dispatches_upload_job(): void
    {
        Queue::fake();
        Storage::fake('public');

        $file = UploadedFile::fake()->image('selfie.jpg', 640, 480);
        $service = new FileUploadService();

        $path = $service->uploadImage($file, 'selfies');

        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
        Queue::assertPushed(UploadFileToR2Job::class, function ($job) use ($path) {
            return $job->path === $path;
        });
    }

    public function test_upload_job_uploads_to_r2_when_configured(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        Config::set('filesystems.disks.r2.bucket', 'my-bucket');
        Config::set('filesystems.disks.r2.key', 'my-key');

        $testPath = 'selfies/test.webp';
        Storage::disk('public')->put($testPath, 'fake-binary-data');

        $job = new UploadFileToR2Job($testPath, 'image/webp');
        $job->handle();

        Storage::disk('r2')->assertExists($testPath);
    }

    public function test_upload_job_skips_when_r2_not_configured(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        Config::set('filesystems.disks.r2.bucket', null);
        Config::set('filesystems.disks.r2.key', null);

        $testPath = 'selfies/test.webp';
        Storage::disk('public')->put($testPath, 'fake-binary-data');

        $job = new UploadFileToR2Job($testPath, 'image/webp');
        $job->handle();

        Storage::disk('r2')->assertMissing($testPath);
    }
}
