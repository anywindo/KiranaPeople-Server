<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EventType;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FridayBreakSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
    }

    public function test_admin_can_access_settings_page(): void
    {
        $admin = User::factory()->create([
            'password_changed_at' => now(),
        ]);
        $admin->assignRole('HR');

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertStatus(200);
        $response->assertSee('Pengaturan Rule Presensi');
        $response->assertSee('Pengaturan Istirahat Hari Jumat');
        $response->assertSee('Pengecualian Presensi');
        $response->assertSee('Simpan Perubahan');
    }

    public function test_admin_can_update_friday_break_settings_independently(): void
    {
        $admin = User::factory()->create([
            'password_changed_at' => now(),
        ]);
        $admin->assignRole('HR');

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'setting_group' => 'friday_break',
            'friday_break_enabled' => '1',
            'friday_break_start' => '11:45',
            'friday_break_end' => '13:15',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pengaturan istirahat hari Jumat berhasil diperbarui.');

        $this->assertEquals('true', Setting::get('friday_break_enabled'));
        $this->assertEquals('11:45', Setting::get('friday_break_start'));
        $this->assertEquals('13:15', Setting::get('friday_break_end'));
    }

    public function test_admin_can_update_attendance_rule_settings_independently(): void
    {
        $admin = User::factory()->create([
            'password_changed_at' => now(),
        ]);
        $admin->assignRole('HR');

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'setting_group' => 'attendance_rule',
            'attendance_rule_enabled' => '1',
            'attendance_rule_minutes' => '20',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pengaturan rule presensi berhasil diperbarui.');

        $this->assertEquals('true', Setting::get('attendance_rule_enabled'));
        $this->assertEquals(20, Setting::get('attendance_rule_minutes'));
    }

    public function test_admin_can_update_exclude_roles_settings_independently(): void
    {
        $admin = User::factory()->create([
            'password_changed_at' => now(),
        ]);
        $admin->assignRole('HR');

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'setting_group' => 'exclude_roles',
            'exclude_roles' => ['Super-admin'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pengaturan pengecualian presensi berhasil diperbarui.');

        $this->assertEquals(['Super-admin'], json_decode(Setting::get('exclude_roles_from_attendance'), true));
    }

    public function test_admin_can_update_friday_break_settings(): void
    {
        $admin = User::factory()->create([
            'password_changed_at' => now(),
        ]);
        $admin->assignRole('HR');

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'attendance_rule_minutes' => 15,
            'friday_break_enabled' => '1',
            'friday_break_start' => '11:45',
            'friday_break_end' => '13:15',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('true', Setting::get('friday_break_enabled'));
        $this->assertEquals('11:45', Setting::get('friday_break_start'));
        $this->assertEquals('13:15', Setting::get('friday_break_end'));
    }

    public function test_friday_break_status_considers_custom_settings_on_friday(): void
    {
        Setting::set('friday_break_enabled', 'true', 'boolean');
        Setting::set('friday_break_start', '11:30', 'string');
        Setting::set('friday_break_end', '13:00', 'string'); // 90 mins

        $user = User::factory()->create();
        $shift = Shift::create([
            'shift_name' => 'Regular',
            'default_start_time' => '08:00',
            'default_end_time' => '17:00',
            'break_start' => '12:00',
            'break_end' => '13:00', // standard break is only 60 mins
        ]);

        // 2026-10-16 is a Friday
        $fridayDate = Carbon::parse('2026-10-16');
        $this->assertTrue($fridayDate->isFriday());

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => $fridayDate->toDateString(),
            'status' => AttendanceStatus::EXACT,
        ]);

        // Employee takes an 80 min break on Friday (11:35 to 12:55)
        AttendanceEvent::create([
            'attendance_id' => $attendance->id,
            'event_type' => EventType::START_BREAK,
            'timestamp' => '2026-10-16 11:35:00',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie_path' => 'selfies/dummy.jpg',
        ]);
        AttendanceEvent::create([
            'attendance_id' => $attendance->id,
            'event_type' => EventType::END_BREAK,
            'timestamp' => '2026-10-16 12:55:00',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie_path' => 'selfies/dummy.jpg',
        ]);

        $attendance->refresh();

        // Under 60 min shift duration, 80 mins would be 'Terlambat'.
        // But under 90 min Friday break duration, 80 mins is 'Tepat Waktu'.
        $breakStatus = $attendance->break_status;
        $this->assertEquals('Tepat Waktu', $breakStatus['label']);
        $this->assertEquals('success', $breakStatus['color']);
    }

    public function test_attendance_service_allows_start_break_at_friday_break_time(): void
    {
        Setting::set('friday_break_enabled', 'true', 'boolean');
        Setting::set('friday_break_start', '11:30', 'string');
        Setting::set('friday_break_end', '13:00', 'string');

        $user = User::factory()->create();
        $shift = Shift::create([
            'shift_name' => 'Regular',
            'default_start_time' => '08:00',
            'default_end_time' => '17:00',
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        $service = app(AttendanceService::class);

        // Simulate Friday 08:05:00 for check-in
        Carbon::setTestNow(Carbon::parse('2026-10-16 08:05:00'));
        $service->recordEvent($user, $shift, EventType::START_SHIFT, [
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie_path' => 'selfies/dummy.jpg',
        ]);

        // Simulate Friday 11:35:00 for break (before standard 12:00 shift break, but after Friday 11:30 break)
        Carbon::setTestNow(Carbon::parse('2026-10-16 11:35:00'));
        $attendance = $service->recordEvent($user, $shift, EventType::START_BREAK, [
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie_path' => 'selfies/dummy.jpg',
        ]);

        $this->assertCount(2, $attendance->events);
        $this->assertEquals(EventType::START_BREAK, $attendance->events->last()->event_type);

        Carbon::setTestNow(); // Reset mock time
    }

    public function test_attendance_service_rejects_start_break_before_friday_break_time(): void
    {
        Setting::set('friday_break_enabled', 'true', 'boolean');
        Setting::set('friday_break_start', '11:30', 'string');
        Setting::set('friday_break_end', '13:00', 'string');

        $user = User::factory()->create();
        $shift = Shift::create([
            'shift_name' => 'Regular',
            'default_start_time' => '08:00',
            'default_end_time' => '17:00',
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        $service = app(AttendanceService::class);

        // Simulate Friday 08:05:00 for check-in
        Carbon::setTestNow(Carbon::parse('2026-10-16 08:05:00'));
        $service->recordEvent($user, $shift, EventType::START_SHIFT, [
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie_path' => 'selfies/dummy.jpg',
        ]);

        // Simulate Friday 11:15:00 (before Friday 11:30 break)
        Carbon::setTestNow(Carbon::parse('2026-10-16 11:15:00'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Belum waktu istirahat (jadwal istirahat mulai: 11:30:00).');

        try {
            $service->recordEvent($user, $shift, EventType::START_BREAK, [
                'latitude' => -6.200000,
                'longitude' => 106.816666,
                'selfie_path' => 'selfies/dummy.jpg',
            ]);
        } finally {
            Carbon::setTestNow(); // Reset mock time
        }
    }
}
