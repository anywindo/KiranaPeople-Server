<?php

namespace App\Console\Commands;

use App\Models\AttendanceEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateStorageToR2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:migrate-r2 {--clean-db : Purge base64 columns from database after uploading to R2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate all avatars and selfies from local/database to Cloudflare R2 bucket and optionally clean database base64 columns';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $r2 = Storage::disk('r2');
        $cleanDb = (bool) $this->option('clean-db');

        $this->info('Starting migration to Cloudflare R2...');

        // 1. Migrate Users' avatars
        $users = User::whereNotNull('avatar')->get();
        $this->info("Found {$users->count()} users with avatars.");
        foreach ($users as $user) {
            $path = ltrim($user->avatar, '/');
            $path = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

            $binary = null;
            if (! empty($user->avatar_base64)) {
                $binary = base64_decode($user->avatar_base64);
            } elseif (file_exists(public_path($path))) {
                $binary = file_get_contents(public_path($path));
            } elseif (file_exists(storage_path('app/public/'.$path))) {
                $binary = file_get_contents(storage_path('app/public/'.$path));
            }

            if ($binary) {
                $r2->put($path, $binary, [
                    'visibility' => 'public',
                    'ContentType' => 'image/webp',
                ]);
                if ($cleanDb && ! empty($user->avatar_base64)) {
                    $user->update(['avatar_base64' => null]);
                }
                $this->line("  [OK] Uploaded avatar: {$path} (".strlen($binary).' bytes)');
            } else {
                $this->warn("  [SKIP] No binary found for avatar: {$path}");
            }
        }

        // 2. Migrate Attendance Events' selfies
        $events = AttendanceEvent::whereNotNull('selfie_path')->get();
        $this->info("Found {$events->count()} attendance events with selfies.");
        foreach ($events as $event) {
            $path = ltrim($event->selfie_path, '/');
            $path = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

            $binary = null;
            if (! empty($event->selfie_base64)) {
                $binary = base64_decode($event->selfie_base64);
            } elseif (file_exists(public_path($path))) {
                $binary = file_get_contents(public_path($path));
            } elseif (file_exists(storage_path('app/public/'.$path))) {
                $binary = file_get_contents(storage_path('app/public/'.$path));
            }

            if ($binary) {
                $r2->put($path, $binary, [
                    'visibility' => 'public',
                    'ContentType' => 'image/webp',
                ]);
                if ($cleanDb && ! empty($event->selfie_base64)) {
                    $event->update(['selfie_base64' => null]);
                }
                $this->line("  [OK] Uploaded selfie: {$path} (".strlen($binary).' bytes)');
            } else {
                $this->warn("  [SKIP] No binary found for selfie: {$path}");
            }
        }

        $this->info('Migration to Cloudflare R2 completed successfully!');

        return self::SUCCESS;
    }
}
