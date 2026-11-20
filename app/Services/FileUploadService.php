<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

class FileUploadService
{
    /**
     * Upload and compress an image (like a selfie).
     *
     * @return string The stored file path
     */
    public function uploadImage(UploadedFile $file, string $directory = 'selfies', int $quality = 70): string
    {
        $path = $this->generateFilePath($directory);
        $binary = $this->compressImage($file, $quality);

        $this->storeLocallyAndOnR2($path, $directory, $binary);

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
        $path = $this->generateFilePath($directory);
        $binary = $this->compressImage($file, $quality);

        $this->storeLocallyAndOnR2($path, $directory, $binary);

        return [
            'path' => $path,
            'base64' => base64_encode($binary),
        ];
    }

    /**
     * Compress and scale an uploaded image to WebP format.
     */
    protected function compressImage(UploadedFile $file, int $quality): string
    {
        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->decodePath($file->getRealPath());
            $image->scaleDown(width: 800, height: 800);
            $encodedImage = $image->encode(new WebpEncoder(quality: $quality));

            return (string) $encodedImage;
        } catch (Throwable $e) {
            Log::warning('Intervention image processing fallback: '.$e->getMessage());

            return (string) file_get_contents($file->getRealPath());
        }
    }

    /**
     * Generate a unique WebP destination path.
     */
    protected function generateFilePath(string $directory): string
    {
        $filename = uniqid().'_'.time().'.webp';

        return trim($directory, '/').'/'.$filename;
    }

    /**
     * Store image binary to both Cloudflare R2 and local storage cache.
     */
    protected function storeLocallyAndOnR2(string $path, string $directory, string $binary): void
    {
        // Ensure local destination directory exists (handles serverless /tmp)
        $this->ensureLocalDirectoryExists($directory);

        // Upload to Cloudflare R2
        try {
            Storage::disk('r2')->put($path, $binary, [
                'visibility' => 'public',
                'ContentType' => 'image/webp',
            ]);
        } catch (Throwable $e) {
            Log::error('R2 upload failed: '.$e->getMessage());
        }

        // Cache locally on public storage disk
        try {
            Storage::disk('public')->put($path, $binary);
        } catch (Throwable $e) {
            Log::error('Local storage put failed: '.$e->getMessage());
        }
    }

    /**
     * Ensure local filesystem path exists.
     */
    protected function ensureLocalDirectoryExists(string $directory): void
    {
        try {
            $root = Storage::disk('public')->path('');
            $targetDir = rtrim($root, '/').'/'.trim($directory, '/');
            if (! is_dir($targetDir)) {
                @mkdir($targetDir, 0777, true);
            }
        } catch (Throwable $e) {
            // Ignore path resolution issues
        }
    }
}
