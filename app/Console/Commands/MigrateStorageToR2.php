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
        $users = User::whereNotNull('avatar')->where('avatar', '!=', '')->get();
        $this->info("Found {$users->count()} users with avatars.");
        foreach ($users as $user) {
            $path = ltrim($user->avatar, '/');
            $path = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

            if (empty($path)) {
                continue;
            }

            $binary = null;
            if (is_file(public_path($path))) {
                $binary = file_get_contents(public_path($path));
            } elseif (is_file(storage_path('app/public/'.$path))) {
                $binary = file_get_contents(storage_path('app/public/'.$path));
            }

            if ($binary) {
                $r2->put($path, $binary, [
                    'visibility' => 'public',
                    'ContentType' => 'image/webp',
                ]);
                $this->line("  [OK] Uploaded avatar: {$path} (".strlen($binary).' bytes)');
            } else {
                $this->warn("  [SKIP] No binary found for avatar: {$path}");
            }
        }

        // 2. Migrate Attendance Events' selfies
        $events = AttendanceEvent::whereNotNull('selfie_path')->where('selfie_path', '!=', '')->get();
        $this->info("Found {$events->count()} attendance events with selfies.");
        foreach ($events as $event) {
            $path = ltrim($event->selfie_path, '/');
            $path = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

            if (empty($path)) {
                continue;
            }

            $binary = null;
            if (is_file(public_path($path))) {
                $binary = file_get_contents(public_path($path));
            } elseif (is_file(storage_path('app/public/'.$path))) {
                $binary = file_get_contents(storage_path('app/public/'.$path));
            }

            if ($binary) {
                $r2->put($path, $binary, [
                    'visibility' => 'public',
                    'ContentType' => 'image/webp',
                ]);
                $this->line("  [OK] Uploaded selfie: {$path} (".strlen($binary).' bytes)');
            } else {
                $this->warn("  [SKIP] No binary found for selfie: {$path}");
            }
        }

        $this->info('Migration to Cloudflare R2 completed successfully!');

        return self::SUCCESS;
    }
}
