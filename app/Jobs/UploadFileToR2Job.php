<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadFileToR2Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [5, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $path,
        public string $contentType = 'image/webp'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Skip if Cloudflare R2 credentials/bucket are not configured
        if (empty(config('filesystems.disks.r2.bucket')) || empty(config('filesystems.disks.r2.key'))) {
            return;
        }

        $localDisk = Storage::disk('public');
        if (! $localDisk->exists($this->path)) {
            Log::warning("UploadFileToR2Job: Local file not found at {$this->path}");
            return;
        }

        $binary = $localDisk->get($this->path);

        Storage::disk('r2')->put($this->path, $binary, [
            'visibility' => 'public',
            'ContentType' => $this->contentType,
        ]);
    }
}
