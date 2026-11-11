<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\AuditLogsExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AuditLogArchive;
use App\Notifications\AuditPurgeOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AuditLogArchiveController extends Controller
{
    /**
     * Display a listing of audit archives and purge management.
     */
    public function index()
    {
        $archives = AuditLogArchive::with('user')->latest('id')->paginate(15);
        $totalLogs = AuditLog::count();

        return view('admin.audit-logs.archives', compact('archives', 'totalLogs'));
    }

    /**
     * Generate an archive file (.xlsx) and store it in server storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $count = AuditLog::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->count();

        if ($count === 0) {
            return back()->with('error', 'Tidak ditemukan rekaman log pada rentang tanggal tersebut untuk diarsipkan.');
        }

        $filename = 'audit-logs-'.$dateFrom.'-sd-'.$dateTo.'-'.time().'.xlsx';
        $filePath = 'archives/'.$filename;

        // Ensure storage directory exists
        Storage::disk('local')->makeDirectory('archives');

        // Store Excel export to local disk
        Excel::store(new AuditLogsExport([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]), $filePath, 'local');

        $archive = AuditLogArchive::create([
            'user_id' => auth()->id(),
            'filename' => $filename,
            'file_path' => $filePath,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'total_records' => $count,
        ]);

        return redirect()->route('admin.audit-logs.archives.index')
            ->with('success', "Arsip berhasil dibuat (#{$archive->id}) berisi {$count} rekaman. Silakan unduh (download) file terlebih dahulu sebelum data dapat dibersihkan.");
    }

    /**
     * Download the archive file and mark downloaded_at timestamp (Gate 1).
     */
    public function download(AuditLogArchive $archive)
    {
        if (! Storage::disk('local')->exists($archive->file_path)) {
            return back()->with('error', 'File arsip fisik tidak ditemukan di server.');
        }

        // Mark downloaded timestamp (Prerequisite Gate 1)
        if (! $archive->isDownloaded()) {
            $archive->update(['downloaded_at' => now()]);
        }

        return Storage::disk('local')->download($archive->file_path, $archive->filename);
    }

    /**
     * Request OTP via email to purge the archived logs (Gate 2 preparation).
     */
    public function requestOtp(Request $request, AuditLogArchive $archive)
    {
        // Security Gate 1: Must be downloaded first!
        if (! $archive->isDownloaded()) {
            $msg = 'Akses ditolak: Anda wajib mengunduh (download) berkas arsip terlebih dahulu sebagai backup sebelum meminta OTP penghapusan.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 403)
                : back()->with('error', $msg);
        }

        // Already purged check
        if ($archive->isPurged()) {
            $msg = 'Data log pada rentang arsip ini sudah dibersihkan sebelumnya.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 400)
                : back()->with('error', $msg);
        }

        $user = auth()->user();
        $cooldownKey = "audit_purge_otp_cooldown_{$user->id}_{$archive->id}";

        // Cooldown check: 60 seconds between OTP requests
        if (Cache::has($cooldownKey)) {
            $msg = 'Mohon tunggu 60 detik sebelum meminta pengiriman kode OTP baru.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 429)
                : back()->with('error', $msg);
        }

        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache for 5 minutes (300 seconds)
        $cacheKey = "audit_purge_otp_{$user->id}_{$archive->id}";
        $attemptsKey = "audit_purge_otp_attempts_{$user->id}_{$archive->id}";

        Cache::put($cacheKey, Hash::make($otp), now()->addMinutes(5));
        Cache::forget($attemptsKey); // Reset failed attempts counter for new OTP
        Cache::put($cooldownKey, true, now()->addSeconds(60));

        $emailSent = false;
        $errorMessage = null;

        try {
            $user->notify(new AuditPurgeOtpNotification($otp, $archive));
            $emailSent = true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[AuditPurgeOTP] Failed sending email to {$user->email}: ".$e->getMessage());
            $errorMessage = $e->getMessage();
        }

        $isDevEnvironment = app()->environment('local', 'testing');

        // In production, failed email is always an error and dev OTP is NEVER returned
        if (! $emailSent && ! $isDevEnvironment) {
            $msg = 'Gagal mengirimkan kode OTP ke email: '.($errorMessage ?? 'Kesalahan pengiriman');

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        $devOtp = ($isDevEnvironment && (! $emailSent || str_ends_with($user->email, '@example.com'))) ? $otp : null;
        $successMsg = $emailSent
            ? "Kode OTP 6-digit telah dikirimkan ke email {$user->email}. Kode berlaku selama 5 menit."
            : 'Mode Uji Coba: Kode OTP berhasil dibuat. Silakan gunakan kode verifikasi di bawah ini.';

        return $request->expectsJson()
            ? response()->json([
                'success' => true,
                'message' => $successMsg,
                'dev_otp' => $devOtp,
            ])
            : back()->with('success', $successMsg)->with('open_otp_modal', $archive->id);
    }

    /**
     * Execute purging of archived audit logs with verified OTP (Gate 2 execution).
     */
    public function purge(Request $request, AuditLogArchive $archive)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ], [
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.size' => 'Kode OTP harus 6 digit angka.',
        ]);

        // Security Gate 1: Must have been downloaded
        if (! $archive->isDownloaded()) {
            return back()->with('error', 'Pembersihan ditolak: Berkas arsip belum pernah diunduh.');
        }

        if ($archive->isPurged()) {
            return back()->with('error', 'Pembersihan ditolak: Data arsip ini sudah pernah dibersihkan.');
        }

        $user = auth()->user();
        $cacheKey = "audit_purge_otp_{$user->id}_{$archive->id}";
        $attemptsKey = "audit_purge_otp_attempts_{$user->id}_{$archive->id}";
        $cooldownKey = "audit_purge_otp_cooldown_{$user->id}_{$archive->id}";

        $cachedHash = Cache::get($cacheKey);

        // Check if OTP exists
        if (! $cachedHash) {
            return back()->with('error', 'Kode OTP tidak ditemukan atau telah kedaluwarsa. Silakan minta kode OTP baru.');
        }

        // Brute-force protection: check maximum attempts (5 tries)
        $attempts = (int) Cache::get($attemptsKey, 0);
        if ($attempts >= 5) {
            Cache::forget($cacheKey);
            Cache::forget($attemptsKey);

            return back()->with('error', 'Batas maksimal 5 kali percobaan OTP telah terlampaui. Kode OTP dibatalkan. Silakan minta kode OTP baru.');
        }

        // Validate OTP hash
        if (! Hash::check($request->otp, $cachedHash)) {
            $attempts++;
            Cache::put($attemptsKey, $attempts, now()->addMinutes(5));
            $remaining = 5 - $attempts;

            if ($remaining <= 0) {
                Cache::forget($cacheKey);
                Cache::forget($attemptsKey);

                return back()->with('error', 'Kode OTP salah. Batas maksimal 5 percobaan telah habis. Silakan minta kode OTP baru.');
            }

            return back()->with('error', "Kode OTP tidak valid. Sisa percobaan: {$remaining} kali.");
        }

        // Execute purge atomically with row-level lock
        try {
            DB::transaction(function () use ($archive, $cacheKey, $attemptsKey, $cooldownKey, $user) {
                // Lock row to prevent race conditions from concurrent purge calls
                $lockedArchive = AuditLogArchive::where('id', $archive->id)->lockForUpdate()->first();

                if (! $lockedArchive || $lockedArchive->isPurged()) {
                    throw new \RuntimeException('Data arsip ini sudah pernah dibersihkan sebelumnya.');
                }

                $dateFrom = $lockedArchive->date_from->toDateString();
                $dateTo = $lockedArchive->date_to->toDateString();

                // Delete the records in the date range
                $deletedCount = AuditLog::whereDate('created_at', '>=', $dateFrom)
                    ->whereDate('created_at', '<=', $dateTo)
                    ->delete();

                // Mark archive as purged
                $lockedArchive->update([
                    'purged_at' => now(),
                ]);

                // Clear OTP & attempt cache keys
                Cache::forget($cacheKey);
                Cache::forget($attemptsKey);
                Cache::forget($cooldownKey);

                // Record this critical administrative action in audit_logs
                AuditLog::create([
                    'user_id' => $user->id,
                    'event' => 'deleted',
                    'auditable_type' => AuditLogArchive::class,
                    'auditable_id' => $lockedArchive->id,
                    'before' => [
                        'archive_id' => $lockedArchive->id,
                        'filename' => $lockedArchive->filename,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'total_records' => $lockedArchive->total_records,
                        'deleted_rows' => $deletedCount,
                    ],
                    'after' => null,
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'created_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses pembersihan log: '.$e->getMessage());
        }

        return redirect()->route('admin.audit-logs.archives.index')
            ->with('success', "Data log sebanyak {$archive->total_records} baris berhasil dibersihkan dari database secara aman.");
    }
}
