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
}
