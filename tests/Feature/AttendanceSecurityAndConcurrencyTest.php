<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceSecurityAndConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Shift $shift;
    protected Location $location;
    protected AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'employee']);

        $this->location = Location::create([
            'name' => 'HQ',
            'latitude' => -6.208800,
            'longitude' => 106.845600,
            'radius' => 100,
        ]);

        $this->shift = Shift::create([
            'shift_name' => 'Regular Shift',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $this->user = User::factory()->create([
            'location_id' => $this->location->id,
            'password_changed_at' => now(),
        ]);
        $this->user->assignRole('employee');

        $this->service = app(AttendanceService::class);
    }

    public function test_rejects_attendance_when_mock_location_detected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Lokasi palsu (Mock Location / Fake GPS) terdeteksi');

        try {
            $this->service->recordEvent($this->user, $this->shift, EventType::START_SHIFT, [
                'latitude' => -6.208800,
                'longitude' => 106.845600,
                'selfie_path' => 'selfies/test.jpg',
                'is_mocked' => '1',
            ]);
        } finally {
            // Verify security violation audit log was written
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $this->user->id,
                'event' => 'security_violation',
            ]);
        }
    }

    public function test_rejects_attendance_when_fake_gps_app_installed_is_flagged(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Lokasi palsu (Mock Location / Fake GPS) terdeteksi');

        $this->service->recordEvent($this->user, $this->shift, EventType::START_SHIFT, [
            'latitude' => -6.208800,
            'longitude' => 106.845600,
            'selfie_path' => 'selfies/test.jpg',
            'fake_gps_app_installed' => true,
        ]);
    }

    public function test_rejects_attendance_when_device_is_rooted_or_hooked(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Perangkat tidak lolos pemeriksaan integritas keamanan');

        try {
            $this->service->recordEvent($this->user, $this->shift, EventType::START_SHIFT, [
                'latitude' => -6.208800,
                'longitude' => 106.845600,
                'selfie_path' => 'selfies/test.jpg',
                'is_rooted' => '1',
            ]);
        } finally {
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $this->user->id,
                'event' => 'security_violation',
            ]);
        }
    }

    public function test_rejects_attendance_when_accuracy_is_negative(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Nilai akurasi GPS tidak valid');

        $this->service->recordEvent($this->user, $this->shift, EventType::START_SHIFT, [
            'latitude' => -6.208800,
            'longitude' => 106.845600,
            'selfie_path' => 'selfies/test.jpg',
            'accuracy' => -5.0,
        ]);
    }

    public function test_rejects_attendance_when_device_timestamp_drift_exceeds_threshold(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Waktu pada perangkat tidak sinkron');

        // 20 minutes in the past
        $skewedTimestamp = now()->subMinutes(20)->toISOString();

        $this->service->recordEvent($this->user, $this->shift, EventType::START_SHIFT, [
            'latitude' => -6.208800,
            'longitude' => 106.845600,
            'selfie_path' => 'selfies/test.jpg',
            'device_timestamp' => $skewedTimestamp,
        ]);
    }

    public function test_accepts_clean_attendance_and_persists_telemetry_and_notes(): void
    {
        $deviceTime = now()->subSeconds(5)->toISOString();

        $attendance = $this->service->recordEvent($this->user, $this->shift, EventType::START_SHIFT, [
            'latitude' => -6.208800,
            'longitude' => 106.845600,
            'selfie_path' => 'selfies/test.jpg',
            'accuracy' => 12.5,
            'altitude' => 45.2,
            'is_mocked' => '0',
            'device_timestamp' => $deviceTime,
            'notes' => 'Masuk pagi tepat waktu',
            'device_telemetry' => [
                'integrity_status' => 'clean',
                'is_rooted' => '0',
            ],
        ]);

        $this->assertNotNull($attendance);
        $event = $attendance->events()->first();

        $this->assertNotNull($event);
        $this->assertEquals('Masuk pagi tepat waktu', $event->notes);
        $this->assertEquals(12.5, (float) $event->accuracy);
        $this->assertEquals(45.2, (float) $event->altitude);
        $this->assertFalse((bool) $event->is_mocked);
        $this->assertNotNull($event->device_timestamp);
        $this->assertEquals(['integrity_status' => 'clean', 'is_rooted' => '0'], $event->device_telemetry);
    }

    public function test_api_store_event_persists_notes_and_telemetry(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        $file = UploadedFile::fake()->image('selfie.jpg', 300, 300);

        $response = $this->actingAs($this->user)->postJson('/api/v1/employee/attendance/events', [
            'shift_id' => $this->shift->id,
            'event_type' => 'START_SHIFT',
            'latitude' => -6.208800,
            'longitude' => 106.845600,
            'accuracy' => 10.2,
            'altitude' => 30.0,
            'is_mocked' => '0',
            'notes' => 'Catatan kehadiran dari mobile',
            'device_timestamp' => now()->toISOString(),
            'integrity_status' => 'clean',
            'selfie' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'meta' => [
                'success' => true,
                'message' => 'Kehadiran berhasil dicatat!',
            ],
            'data' => [
                'event_type' => 'START_SHIFT',
            ]
        ]);

        $this->assertDatabaseHas('attendance_events', [
            'notes' => 'Catatan kehadiran dari mobile',
            'is_mocked' => false,
        ]);

        // Check that today endpoint returns notes and accuracy
        $todayResponse = $this->actingAs($this->user)->getJson('/api/v1/employee/attendance/today');
        $todayResponse->assertStatus(200);
        $todayResponse->assertJsonFragment([
            'notes' => 'Catatan kehadiran dari mobile',
            'is_mocked' => false,
        ]);
    }
}
