<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
    }

    public function test_admin_dashboard_renders_with_analytics_and_trend_data()
    {
        $hr = User::factory()->create();
        $hr->assignRole('HR');

        $location = Location::create([
            'name' => 'Kantor Pusat',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'radius_meters' => 100,
        ]);

        $shift = Shift::create([
            'shift_name' => 'Pagi',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
        ]);

        $employee = User::factory()->create([
            'location_id' => $location->id,
            'is_active' => true,
        ]);
        $employee->assignRole('Employee');

        $today = Carbon::today();

        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
            'status' => AttendanceStatus::EXACT,
        ]);

        AttendanceEvent::create([
            'attendance_id' => $attendance->id,
            'event_type' => EventType::START_SHIFT,
            'timestamp' => $today->copy()->setTime(7, 55, 0),
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie_path' => 'selfies/test.jpg',
        ]);

        $response = $this->actingAs($hr, 'web')->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
        $response->assertViewHasAll([
            'totalEmployees',
            'presentToday',
            'onTimeToday',
            'lateCountToday',
            'onTimeRate',
            'trendData',
            'liveActivity',
        ]);

        $viewData = $response->viewData('trendData');
        $this->assertIsArray($viewData);
        $this->assertArrayHasKey('labels', $viewData);
        $this->assertArrayHasKey('on_time', $viewData);
        $this->assertArrayHasKey('late', $viewData);
        $this->assertArrayHasKey('absent', $viewData);
        $this->assertCount(7, $viewData['labels']);
    }

    public function test_late_and_early_employees_minutes_are_clean_integers_without_decimals()
    {
        $hr = User::factory()->create();
        $hr->assignRole('HR');

        $location = Location::create([
            'name' => 'Kantor Cabang',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'radius_meters' => 100,
        ]);

        $shift = Shift::create([
            'shift_name' => 'Reguler',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
        ]);

        $lateEmployee = User::factory()->create([
            'name' => 'Late Guy',
            'location_id' => $location->id,
            'is_active' => true,
        ]);
        $lateEmployee->assignRole('Employee');

        $today = Carbon::today();

        $lateAttendance = Attendance::create([
            'user_id' => $lateEmployee->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
            'status' => AttendanceStatus::LATE,
        ]);

        // Clock in at 08:52:37 -> exactly 52 minutes and 37 seconds late
        AttendanceEvent::create([
            'attendance_id' => $lateAttendance->id,
            'event_type' => EventType::START_SHIFT,
            'timestamp' => $today->copy()->setTime(8, 52, 37),
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie_path' => 'selfies/late.jpg',
        ]);

        // Clock out early at 16:15:20 -> exactly 44 minutes and 40 seconds early
        AttendanceEvent::create([
            'attendance_id' => $lateAttendance->id,
            'event_type' => EventType::END_SHIFT,
            'timestamp' => $today->copy()->setTime(16, 15, 20),
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie_path' => 'selfies/early.jpg',
        ]);

        $response = $this->actingAs($hr, 'web')->get('/admin/dashboard');

        $response->assertStatus(200);

        $lateEmployees = $response->viewData('lateEmployees');
        $this->assertNotEmpty($lateEmployees);
        $firstLate = $lateEmployees->first();
        $this->assertSame(52, $firstLate->late_minutes);
        $this->assertIsInt($firstLate->late_minutes);

        $earlyEmployees = $response->viewData('earlyEmployees');
        $this->assertNotEmpty($earlyEmployees);
        $firstEarly = $earlyEmployees->first();
        $this->assertSame(44, $firstEarly->early_minutes);
        $this->assertIsInt($firstEarly->early_minutes);

        $content = $response->getContent();
        $this->assertStringContainsString('52 Menit', $content);
        $this->assertStringContainsString('44 Menit', $content);
        $this->assertStringNotContainsString('52.', $content);
        $this->assertStringNotContainsString('44.', $content);
    }

    public function test_late_minutes_calculates_against_attendance_rule_cutoff_when_enabled()
    {
        $hr = User::factory()->create();
        $hr->assignRole('HR');

        \App\Models\Setting::set('attendance_rule_enabled', 'true', 'boolean');
        \App\Models\Setting::set('attendance_rule_minutes', 15, 'integer');

        $location = Location::create([
            'name' => 'Kantor Utama',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'radius_meters' => 100,
        ]);

        $shift = Shift::create([
            'shift_name' => 'Shift 10',
            'default_start_time' => '10:00:00',
            'default_end_time' => '17:00:00',
        ]);

        $employee = User::factory()->create([
            'name' => 'Alexander',
            'location_id' => $location->id,
            'is_active' => true,
        ]);
        $employee->assignRole('Employee');

        $today = Carbon::today();

        $lateAttendance = Attendance::create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
            'status' => AttendanceStatus::LATE,
        ]);

        // Clock in at 09:54:00 -> cutoff is 09:45:00, so exactly 9 minutes late
        AttendanceEvent::create([
            'attendance_id' => $lateAttendance->id,
            'event_type' => EventType::START_SHIFT,
            'timestamp' => $today->copy()->setTime(9, 54, 0),
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie_path' => 'selfies/alexander.jpg',
        ]);

        $response = $this->actingAs($hr, 'web')->get('/admin/dashboard');

        $response->assertStatus(200);

        $lateEmployees = $response->viewData('lateEmployees');
        $this->assertNotEmpty($lateEmployees);
        $firstLate = $lateEmployees->first();
        $this->assertSame(9, $firstLate->late_minutes);

        $content = $response->getContent();
        $this->assertStringContainsString('9 Menit', $content);
    }
}
