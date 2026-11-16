<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBreakIndexDefaultDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
    }

    public function test_breaks_index_defaults_to_today_when_no_date_param_is_passed()
    {
        $hr = User::factory()->create();
        $hr->assignRole('HR');

        $employee1 = User::factory()->create();
        $employee2 = User::factory()->create();

        $shift = \App\Models\Shift::create([
            'shift_name' => 'Pagi',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Break today
        $attToday = Attendance::create([
            'user_id' => $employee1->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
        ]);
        AttendanceEvent::create([
            'attendance_id' => $attToday->id,
            'event_type' => EventType::START_BREAK,
            'timestamp' => $today->copy()->setTime(12, 0, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/today_break.jpg',
        ]);

        // Break yesterday
        $attYesterday = Attendance::create([
            'user_id' => $employee2->id,
            'shift_id' => $shift->id,
            'date' => $yesterday->toDateString(),
        ]);
        AttendanceEvent::create([
            'attendance_id' => $attYesterday->id,
            'event_type' => EventType::START_BREAK,
            'timestamp' => $yesterday->copy()->setTime(12, 0, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/yesterday_break.jpg',
        ]);

        // 1. Visit without date param -> should default to today and only show today's breaks
        $response = $this->actingAs($hr, 'web')->get('/admin/breaks');
        $response->assertStatus(200);
        $response->assertViewHas('selectedDate', $today->toDateString());

        $attendances = $response->viewData('attendances');
        $this->assertCount(1, $attendances);
        $this->assertEquals($attToday->id, $attendances->first()->id);

        // 2. Visit with explicitly empty date param -> should show all dates
        $responseAll = $this->actingAs($hr, 'web')->get('/admin/breaks?date=');
        $responseAll->assertStatus(200);
        $responseAll->assertViewHas('selectedDate', '');

        $attendancesAll = $responseAll->viewData('attendances');
        $this->assertCount(2, $attendancesAll);

        // 3. Visit with specific date param -> should filter by that date
        $responseYesterday = $this->actingAs($hr, 'web')->get('/admin/breaks?date='.$yesterday->toDateString());
        $responseYesterday->assertStatus(200);
        $responseYesterday->assertViewHas('selectedDate', $yesterday->toDateString());

        $attendancesYesterday = $responseYesterday->viewData('attendances');
        $this->assertCount(1, $attendancesYesterday);
        $this->assertEquals($attYesterday->id, $attendancesYesterday->first()->id);
    }
}
