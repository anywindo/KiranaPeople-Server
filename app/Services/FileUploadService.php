<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class FileUploadService
{
    /**
     * Upload and compress an image (like a selfie).
     *
     * @return string The stored file path
     */
    public function uploadImage(UploadedFile $file, string $directory = 'selfies', int $quality = 70): string
    {
        // Convert to WebP format
        $filename = uniqid().'_'.time().'.webp';
        $path = $directory.'/'.$filename;

        // Process image with Intervention Image
        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->decodePath($file->getRealPath());

            // Resize to a max dimension to save space while keeping aspect ratio
            $image->scaleDown(width: 800, height: 800);

            // Compress and encode to webp
            $encodedImage = $image->encode(new WebpEncoder(quality: $quality));
            $binary = (string) $encodedImage;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Intervention image processing fallback: '.$e->getMessage());
            $binary = file_get_contents($file->getRealPath());
        }

        // Ensure destination directory exists on disk (handles /tmp in serverless)
        try {
            $root = Storage::disk('public')->path('');
            $targetDir = rtrim($root, '/').'/'.trim($directory, '/');
            if (! is_dir($targetDir)) {
                @mkdir($targetDir, 0777, true);
            }
        } catch (\Throwable $e) {
            // Ignore path resolution issues
        }

        // Store to local disk (/tmp or storage/app/public) immediately
        try {
            Storage::disk('public')->put($path, $binary);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Local storage put failed: '.$e->getMessage());
        }

        // Asynchronously offload upload to Cloudflare R2 via Queue Job
        try {
            \App\Jobs\UploadFileToR2Job::dispatch($path, 'image/webp');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dispatch UploadFileToR2Job failed: '.$e->getMessage());
        }

        return $path;
    }

    /**
     * Upload and compress an image, storing to Cloudflare R2 and returning
     * both storage path and base64 string for backward compatibility.
     *
     * @return array{path: string, base64: string}
     */
    public function uploadImageWithBase64(UploadedFile $file, string $directory = 'selfies', int $quality = 65): array
    {
        $filename = uniqid().'_'.time().'.webp';
        $path = $directory.'/'.$filename;

        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->decodePath($file->getRealPath());
            $image->scaleDown(width: 800, height: 800);
            $encodedImage = $image->encode(new WebpEncoder(quality: $quality));
            $binary = (string) $encodedImage;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Intervention image processing fallback in uploadImageWithBase64: '.$e->getMessage());
            $binary = file_get_contents($file->getRealPath());
        }

        // Store to local disk immediately
        try {
            Storage::disk('public')->put($path, $binary);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Local storage put failed in uploadImageWithBase64: '.$e->getMessage());
        }

        // Asynchronously offload upload to Cloudflare R2 via Queue Job
        try {
            \App\Jobs\UploadFileToR2Job::dispatch($path, 'image/webp');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dispatch UploadFileToR2Job failed in uploadImageWithBase64: '.$e->getMessage());
        }

        return [
            'path' => $path,
            'base64' => base64_encode($binary),
        ];
    }
}
