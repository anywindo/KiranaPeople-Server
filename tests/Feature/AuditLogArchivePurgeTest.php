<?php

use App\Models\AuditLog;
use App\Models\AuditLogArchive;
use App\Models\Location;
use App\Models\User;
use App\Notifications\AuditPurgeOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Storage::fake('local');
});

test('non-superadmin cannot access archives page or purge endpoints', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.audit-logs.archives.index'));
    $response->assertStatus(403);
});

test('superadmin can create an archive for logs in a date range', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('super-admin');

    // Create a location to generate audit log
    Location::create([
        'name' => 'Lokasi Test Arsip',
        'latitude' => -6.1,
        'longitude' => 106.8,
        'radius' => 50,
    ]);

    $today = now()->toDateString();

    $response = $this->actingAs($superadmin)->post(route('admin.audit-logs.archives.store'), [
        'date_from' => $today,
        'date_to' => $today,
    ]);

    $response->assertRedirect(route('admin.audit-logs.archives.index'));

    $archive = AuditLogArchive::latest('id')->first();
    expect($archive)->not->toBeNull();
    expect($archive->total_records)->toBeGreaterThan(0);
    expect($archive->isDownloaded())->toBeFalse();
    expect($archive->isPurged())->toBeFalse();
    expect(Storage::disk('local')->exists($archive->file_path))->toBeTrue();
});

test('gate 1: requesting OTP is rejected if archive has not been downloaded', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('super-admin');

    $archive = AuditLogArchive::create([
        'user_id' => $superadmin->id,
        'filename' => 'test.xlsx',
        'file_path' => 'archives/test.xlsx',
        'date_from' => now()->subDays(10),
        'date_to' => now()->subDays(5),
        'total_records' => 10,
        'downloaded_at' => null,
    ]);

    $response = $this->actingAs($superadmin)->postJson(route('admin.audit-logs.archives.request-otp', $archive));
    $response->assertStatus(403);
    $response->assertJsonPath('success', false);
});

test('downloading archive sets downloaded_at timestamp', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('super-admin');

    Storage::disk('local')->put('archives/sample.xlsx', 'dummy-excel-content');

    $archive = AuditLogArchive::create([
        'user_id' => $superadmin->id,
        'filename' => 'sample.xlsx',
        'file_path' => 'archives/sample.xlsx',
        'date_from' => now()->subDays(10),
        'date_to' => now()->subDays(5),
        'total_records' => 5,
        'downloaded_at' => null,
    ]);

    $response = $this->actingAs($superadmin)->get(route('admin.audit-logs.archives.download', $archive));
    $response->assertOk();

    $archive->refresh();
    expect($archive->isDownloaded())->toBeTrue();
    expect($archive->downloaded_at)->not->toBeNull();
});

test('gate 2: request OTP sends email notification after download', function () {
    Notification::fake();

    $superadmin = User::factory()->create();
    $superadmin->assignRole('super-admin');

    $archive = AuditLogArchive::create([
        'user_id' => $superadmin->id,
        'filename' => 'sample.xlsx',
        'file_path' => 'archives/sample.xlsx',
        'date_from' => now()->subDays(10),
        'date_to' => now()->subDays(5),
        'total_records' => 5,
        'downloaded_at' => now(),
    ]);

    $response = $this->actingAs($superadmin)->postJson(route('admin.audit-logs.archives.request-otp', $archive));
    $response->assertOk();
    $response->assertJsonPath('success', true);

    Notification::assertSentTo($superadmin, AuditPurgeOtpNotification::class);
});

test('gate 2: purge is rejected if OTP is invalid or expired', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('super-admin');

    $archive = AuditLogArchive::create([
        'user_id' => $superadmin->id,
        'filename' => 'sample.xlsx',
        'file_path' => 'archives/sample.xlsx',
        'date_from' => now()->subDays(10),
        'date_to' => now()->subDays(5),
        'total_records' => 5,
        'downloaded_at' => now(),
    ]);

    // Store correct OTP in cache
    Cache::put("audit_purge_otp_{$superadmin->id}_{$archive->id}", Hash::make('123456'), now()->addMinutes(5));

    // Submit wrong OTP
    $response = $this->actingAs($superadmin)->post(route('admin.audit-logs.archives.purge', $archive), [
        'otp' => '999999',
    ]);

    $response->assertSessionHas('error');
    $archive->refresh();
    expect($archive->isPurged())->toBeFalse();
});

test('gate 2: purge successfully deletes logs and records audit trail when OTP is valid', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('super-admin');

    // Create a log in the target range
    $oldLog = AuditLog::create([
        'user_id' => $superadmin->id,
        'event' => 'created',
        'auditable_type' => Location::class,
        'auditable_id' => 9999,
        'created_at' => now()->subDays(7),
    ]);

    // Create a log outside the target range
    $recentLog = AuditLog::create([
        'user_id' => $superadmin->id,
        'event' => 'created',
        'auditable_type' => Location::class,
        'auditable_id' => 8888,
        'created_at' => now(),
    ]);

    $archive = AuditLogArchive::create([
        'user_id' => $superadmin->id,
        'filename' => 'sample.xlsx',
        'file_path' => 'archives/sample.xlsx',
        'date_from' => now()->subDays(10)->toDateString(),
        'date_to' => now()->subDays(5)->toDateString(),
        'total_records' => 1,
        'downloaded_at' => now(),
    ]);

    Cache::put("audit_purge_otp_{$superadmin->id}_{$archive->id}", Hash::make('654321'), now()->addMinutes(5));

    $response = $this->actingAs($superadmin)->post(route('admin.audit-logs.archives.purge', $archive), [
        'otp' => '654321',
    ]);

    $response->assertRedirect(route('admin.audit-logs.archives.index'));
    $response->assertSessionHas('success');

    $archive->refresh();
    expect($archive->isPurged())->toBeTrue();

    // Verify targeted log is deleted from database
    expect(AuditLog::find($oldLog->id))->toBeNull();

    // Verify log outside range is untouched
    expect(AuditLog::find($recentLog->id))->not->toBeNull();

    // Verify audit log entry for the purge action itself exists
    $purgeAuditLog = AuditLog::where('auditable_type', AuditLogArchive::class)
        ->where('auditable_id', $archive->id)
        ->where('event', 'deleted')
        ->first();

    expect($purgeAuditLog)->not->toBeNull();
});

test('gate 2: requesting OTP enforces 60 seconds cooldown', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('super-admin');

    $archive = AuditLogArchive::create([
        'user_id' => $superadmin->id,
        'filename' => 'sample.xlsx',
        'file_path' => 'archives/sample.xlsx',
        'date_from' => now()->subDays(10),
        'date_to' => now()->subDays(5),
        'total_records' => 5,
        'downloaded_at' => now(),
    ]);

    // First request succeeds
    $res1 = $this->actingAs($superadmin)->postJson(route('admin.audit-logs.archives.request-otp', $archive));
    $res1->assertOk();

    // Immediate second request is throttled with 429
    $res2 = $this->actingAs($superadmin)->postJson(route('admin.audit-logs.archives.request-otp', $archive));
    $res2->assertStatus(429);
    $res2->assertJsonPath('success', false);
});

test('gate 2: purge locks out and invalidates OTP after 5 failed attempts', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('super-admin');

    $archive = AuditLogArchive::create([
        'user_id' => $superadmin->id,
        'filename' => 'sample.xlsx',
        'file_path' => 'archives/sample.xlsx',
        'date_from' => now()->subDays(10),
        'date_to' => now()->subDays(5),
        'total_records' => 5,
        'downloaded_at' => now(),
    ]);

    Cache::put("audit_purge_otp_{$superadmin->id}_{$archive->id}", Hash::make('123456'), now()->addMinutes(5));

    // 4 failed attempts
    for ($i = 1; $i <= 4; $i++) {
        $res = $this->actingAs($superadmin)->post(route('admin.audit-logs.archives.purge', $archive), [
            'otp' => '000000',
        ]);
        $res->assertSessionHas('error');
    }

    // 5th failed attempt should invalidate the OTP
    $res5 = $this->actingAs($superadmin)->post(route('admin.audit-logs.archives.purge', $archive), [
        'otp' => '000000',
    ]);
    $res5->assertSessionHas('error');

    // OTP in cache should now be destroyed
    expect(Cache::get("audit_purge_otp_{$superadmin->id}_{$archive->id}"))->toBeNull();

    // Even if user now submits the correct OTP ('123456'), it must fail
    $resAfterLock = $this->actingAs($superadmin)->post(route('admin.audit-logs.archives.purge', $archive), [
        'otp' => '123456',
    ]);
    $resAfterLock->assertSessionHas('error');
    $archive->refresh();
    expect($archive->isPurged())->toBeFalse();
});
