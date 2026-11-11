<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceWebPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'employee']);
        Role::firstOrCreate(['name' => 'superadmin']);
    }

    public function test_employee_can_view_redesigned_homepage()
    {
        $shift = Shift::create([
            'shift_name' => 'Morning Shift',
            'default_start_time' => '08:00:00',
            'default_end_time' => '16:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $location = Location::create([
            'name' => 'Head Office',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius' => 100,
        ]);

        $user = User::factory()->create([
            'name' => 'Alexander Balmaceda José',
            'password_changed_at' => now(),
            'location_id' => $location->id,
        ]);
        $user->assignRole('employee');

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertSee('KIRANA GROUP');
        $response->assertSee('Alexander Balmaceda José');
        $response->assertSee('Kerja');
        $response->assertSee('Istirahat');
        $response->assertSee('Morning Shift');
        $response->assertSee('08:00');
        $response->assertSee('16:00');
        $response->assertSee('Masuk Kerja');
        $response->assertSee('Aktivitas Hari Ini');
        $response->assertSee('Belum ada riwayat presensi hari ini.');
        $response->assertSee('Beranda');
        $response->assertSee('Peta');
        $response->assertSee('Riwayat');
        $response->assertSee('Profil');
    }

    public function test_employee_sees_start_time_and_pulang_kerja_when_checked_in()
    {
        $shift = Shift::create([
            'shift_name' => 'Morning Shift',
            'default_start_time' => '08:00:00',
            'default_end_time' => '16:00:00',
        ]);

        $location = Location::create([
            'name' => 'Head Office',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius' => 100,
        ]);

        $user = User::factory()->create([
            'password_changed_at' => now(),
            'location_id' => $location->id,
        ]);
        $user->assignRole('employee');

        $attendance = \App\Models\Attendance::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today(),
            'status' => 'EXACT',
            'shift_id' => $shift->id,
        ]);

        \App\Models\AttendanceEvent::create([
            'attendance_id' => $attendance->id,
            'event_type' => 'START_SHIFT',
            'timestamp' => \Carbon\Carbon::today()->setHour(8)->setMinute(5),
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'selfie_path' => 'selfies/test.jpg',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertSee('08:05');
        $response->assertSee('Pulang Kerja');
        $response->assertSee('Titik Lokasi');
    }
}
