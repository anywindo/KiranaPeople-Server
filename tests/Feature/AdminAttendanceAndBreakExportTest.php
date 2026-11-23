<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Exports\Admin\AttendanceExport;
use App\Exports\Admin\BreakExport;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAttendanceAndBreakExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    }

    public function test_datatable_renders_include_all_employees_checkbox_and_export_modal_does_not_contain_dropdown()
    {
        $hr = User::factory()->create();
        $hr->assignRole('HR');

        $responseAttendance = $this->actingAs($hr)->get(route('admin.attendances.index'));
        $responseAttendance->assertStatus(200);
        $responseAttendance->assertSee('type="checkbox" name="include_all_employees"', false);
        $responseAttendance->assertSee('Include Semua Karyawan');
        $responseAttendance->assertSee('name="hide_status"', false);
        $responseAttendance->assertSee('id="attendance_color_coded_btn"', false);
        $responseAttendance->assertDontSee('<select class="form-select" name="include_all_employees"', false);

        $responseBreak = $this->actingAs($hr)->get(route('admin.breaks.index'));
        $responseBreak->assertStatus(200);
        $responseBreak->assertSee('type="checkbox" name="include_all_employees"', false);
        $responseBreak->assertSee('Include Semua Karyawan');
        $responseBreak->assertSee('name="hide_status"', false);
        $responseBreak->assertSee('id="break_color_coded_btn"', false);
        $responseBreak->assertDontSee('<select class="form-select" name="include_all_employees"', false);
    }

    public function test_attendance_datatable_includes_all_employees_when_checkbox_is_checked()
    {
        $shift = Shift::create([
            'shift_name' => 'Pagi',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
        ]);

        $hr = User::factory()->create();
        $hr->assignRole('HR');

        $attendedUser = User::factory()->create(['name' => 'Budi Hadir', 'is_active' => true]);
        $mangkirUser = User::factory()->create(['name' => 'Siti Mangkir', 'is_active' => true]);

        $today = Carbon::today();
        $att = Attendance::create([
            'user_id' => $attendedUser->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
        ]);
        AttendanceEvent::create([
            'attendance_id' => $att->id,
            'event_type' => EventType::START_SHIFT,
            'timestamp' => $today->copy()->setTime(8, 0, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/test.jpg',
        ]);

        // When unchecked: Siti Mangkir is not visible in datatable
        $resUnchecked = $this->actingAs($hr)->get(route('admin.attendances.index', ['date' => $today->toDateString()]));
        $resUnchecked->assertStatus(200);
        $resUnchecked->assertSee('Budi Hadir');
        $resUnchecked->assertDontSee('Siti Mangkir');

        // When checked: Siti Mangkir is visible with Mangkir status
        $resChecked = $this->actingAs($hr)->get(route('admin.attendances.index', [
            'date' => $today->toDateString(),
            'include_all_employees' => '1',
        ]));
        $resChecked->assertStatus(200);
        $resChecked->assertSee('Budi Hadir');
        $resChecked->assertSee('Siti Mangkir');
        $resChecked->assertSee('Mangkir / Belum Presensi');
    }

    public function test_attendance_export_includes_mangkir_employees_by_default()
    {
        $shift = Shift::create([
            'shift_name' => 'Pagi',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
        ]);

        $attendedUser = User::factory()->create(['name' => 'Budi Santoso', 'is_active' => true]);
        $mangkirUser = User::factory()->create(['name' => 'Siti Mangkir', 'is_active' => true]);

        $today = Carbon::today();
        $att = Attendance::create([
            'user_id' => $attendedUser->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
        ]);
        AttendanceEvent::create([
            'attendance_id' => $att->id,
            'event_type' => EventType::START_SHIFT,
            'timestamp' => $today->copy()->setTime(8, 0, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/test.jpg',
        ]);

        // Default export (includeAll = true)
        $export = new AttendanceExport($today->toDateString(), $today->toDateString(), null, null, null, true);
        $collection = $export->collection();

        $names = $collection->map(function ($item) {
            return $item->user->name;
        })->all();

        $this->assertContains('Budi Santoso', $names);
        $this->assertContains('Siti Mangkir', $names);

        $mapped = $collection->map(fn ($item) => $export->map($item));
        $mangkirRow = $mapped->first(fn ($row) => $row[3] === 'Siti Mangkir');
        $this->assertNotNull($mangkirRow);
        $this->assertEquals('Mangkir / Belum Presensi', $mangkirRow[9]);
        $this->assertEquals('-', $mangkirRow[7]); // Masuk aktual
        $this->assertEquals('-', $mangkirRow[8]); // Keluar aktual
    }

    public function test_attendance_export_can_exclude_mangkir_when_disabled()
    {
        $shift = Shift::create([
            'shift_name' => 'Pagi',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
        ]);

        $attendedUser = User::factory()->create(['name' => 'Budi Santoso', 'is_active' => true]);
        $mangkirUser = User::factory()->create(['name' => 'Siti Mangkir', 'is_active' => true]);

        $today = Carbon::today();
        $att = Attendance::create([
            'user_id' => $attendedUser->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
        ]);
        AttendanceEvent::create([
            'attendance_id' => $att->id,
            'event_type' => EventType::START_SHIFT,
            'timestamp' => $today->copy()->setTime(8, 0, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/test.jpg',
        ]);

        // Disabled includeAll (includeAll = false)
        $export = new AttendanceExport($today->toDateString(), $today->toDateString(), null, null, null, false);
        $collection = $export->collection();

        $names = $collection->map(function ($item) {
            return $item->user->name;
        })->all();

        $this->assertContains('Budi Santoso', $names);
        $this->assertNotContains('Siti Mangkir', $names);
    }

    public function test_break_export_includes_all_employees_by_default()
    {
        $shift = Shift::create([
            'shift_name' => 'Pagi',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
        ]);

        $breakUser = User::factory()->create(['name' => 'Budi Istirahat', 'is_active' => true]);
        $noBreakUser = User::factory()->create(['name' => 'Doni Kerja Terus', 'is_active' => true]);
        $mangkirUser = User::factory()->create(['name' => 'Siti Mangkir', 'is_active' => true]);

        $today = Carbon::today();

        // User with break
        $attBreak = Attendance::create([
            'user_id' => $breakUser->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
        ]);
        AttendanceEvent::create([
            'attendance_id' => $attBreak->id,
            'event_type' => EventType::START_SHIFT,
            'timestamp' => $today->copy()->setTime(8, 0, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/b1.jpg',
        ]);
        AttendanceEvent::create([
            'attendance_id' => $attBreak->id,
            'event_type' => EventType::START_BREAK,
            'timestamp' => $today->copy()->setTime(12, 0, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/b2.jpg',
        ]);
        AttendanceEvent::create([
            'attendance_id' => $attBreak->id,
            'event_type' => EventType::END_BREAK,
            'timestamp' => $today->copy()->setTime(12, 45, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/b3.jpg',
        ]);

        // User who attended but took no break
        $attNoBreak = Attendance::create([
            'user_id' => $noBreakUser->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
        ]);
        AttendanceEvent::create([
            'attendance_id' => $attNoBreak->id,
            'event_type' => EventType::START_SHIFT,
            'timestamp' => $today->copy()->setTime(8, 0, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/nb.jpg',
        ]);

        // Default break export (includeAll = true)
        $export = new BreakExport($today->toDateString(), $today->toDateString(), null, null, null, true);
        $collection = $export->collection();

        $names = $collection->map(function ($item) {
            return $item->user->name;
        })->all();

        $this->assertContains('Budi Istirahat', $names);
        $this->assertContains('Doni Kerja Terus', $names);
        $this->assertContains('Siti Mangkir', $names);

        $mapped = $collection->map(fn ($item) => $export->map($item));

        $breakRow = $mapped->first(fn ($row) => $row[3] === 'Budi Istirahat');
        $this->assertNotNull($breakRow);
        $this->assertEquals('12:00:00', $breakRow[7]); // Mulai
        $this->assertEquals('12:45:00', $breakRow[8]); // Selesai
        $this->assertEquals('00:45:00', $breakRow[9]); // Durasi

        $noBreakRow = $mapped->first(fn ($row) => $row[3] === 'Doni Kerja Terus');
        $this->assertNotNull($noBreakRow);
        $this->assertEquals('-', $noBreakRow[7]);
        $this->assertEquals('-', $noBreakRow[8]);
        $this->assertEquals('Tidak Istirahat', $noBreakRow[10]);

        $mangkirRow = $mapped->first(fn ($row) => $row[3] === 'Siti Mangkir');
        $this->assertNotNull($mangkirRow);
        $this->assertEquals('-', $mangkirRow[7]);
        $this->assertEquals('Mangkir / Belum Presensi', $mangkirRow[10]);
    }

    public function test_controller_export_endpoint_triggers_excel_download_with_default_include_all()
    {
        Excel::fake();

        $hr = User::factory()->create();
        $hr->assignRole('HR');

        $this->actingAs($hr)->get(route('admin.attendances.export', ['export_type' => 'today']))
            ->assertStatus(200);

        Excel::assertDownloaded('attendance_'.date('Y-m-d_H-i-s').'.xlsx', function (AttendanceExport $export) {
            $reflector = new \ReflectionClass($export);
            $prop = $reflector->getProperty('includeAll');
            $prop->setAccessible(true);

            return $prop->getValue($export) === true;
        });

        $this->actingAs($hr)->get(route('admin.breaks.export', ['export_type' => 'today']))
            ->assertStatus(200);

        Excel::assertDownloaded('break_history_'.date('Y-m-d_H-i-s').'.xlsx', function (BreakExport $export) {
            $reflector = new \ReflectionClass($export);
            $prop = $reflector->getProperty('includeAll');
            $prop->setAccessible(true);

            return $prop->getValue($export) === true;
        });
    }

    public function test_attendance_export_applies_yellow_header_and_clean_rows_without_colorcoding()
    {
        $export = new AttendanceExport;
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            $export->headings(),
            [1, 'EMP01', '3201', 'Budi Santoso', 'Kantor', '2026-11-16', 'Pagi', '08:00:00', '17:00:00', 'Hadir'],
            [2, 'EMP02', '3202', 'Siti Mangkir', 'Kantor', '2026-11-16', 'Pagi', '-', '-', 'Mangkir / Belum Presensi'],
            [3, 'EMP03', '3203', 'Agus Lupa', 'Kantor', '2026-11-16', 'Pagi', '08:00:00', '-', 'Hadir (Lupa Checkout)'],
        ]);

        $export->styles($sheet);

        // Header tests
        $this->assertEquals('FFFFE599', $sheet->getStyle('A1')->getFill()->getStartColor()->getARGB());
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
        $this->assertEquals(28, $sheet->getRowDimension(1)->getRowHeight());

        // Full table styling
        $this->assertEquals(22, $sheet->getRowDimension(2)->getRowHeight());
        $this->assertEquals(22, $sheet->getRowDimension(3)->getRowHeight());
        $this->assertEquals(22, $sheet->getRowDimension(4)->getRowHeight());

        // Row color-coding should NOT be applied (no colored background fill)
        $this->assertEquals(Fill::FILL_NONE, $sheet->getStyle('A3')->getFill()->getFillType());
        $this->assertEquals(Fill::FILL_NONE, $sheet->getStyle('A4')->getFill()->getFillType());
    }

    public function test_break_export_applies_yellow_header_and_clean_rows_without_colorcoding()
    {
        $export = new BreakExport;
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            $export->headings(),
            [1, 'EMP01', '3201', 'Budi Istirahat', 'Kantor', '2026-11-16', 'Pagi', '12:00:00', '12:45:00', '00:45:00', 'Tepat Waktu'],
            [2, 'EMP02', '3202', 'Siti Mangkir', 'Kantor', '2026-11-16', 'Pagi', '-', '-', '-', 'Mangkir / Belum Presensi'],
            [3, 'EMP03', '3203', 'Doni NoBreak', 'Kantor', '2026-11-16', 'Pagi', '-', '-', '-', 'Tidak Istirahat'],
            [4, 'EMP04', '3204', 'Agus Sedang', 'Kantor', '2026-11-16', 'Pagi', '12:00:00', '-', '-', 'Sedang Istirahat'],
        ]);

        $export->styles($sheet);

        // Header tests
        $this->assertEquals('FFFFE599', $sheet->getStyle('A1')->getFill()->getStartColor()->getARGB());
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
        $this->assertEquals(28, $sheet->getRowDimension(1)->getRowHeight());

        // Full table styling
        $this->assertEquals(22, $sheet->getRowDimension(2)->getRowHeight());
        $this->assertEquals(22, $sheet->getRowDimension(3)->getRowHeight());
        $this->assertEquals(22, $sheet->getRowDimension(4)->getRowHeight());
        $this->assertEquals(22, $sheet->getRowDimension(5)->getRowHeight());

        // Row color-coding should NOT be applied (no colored background fill)
        $this->assertEquals(Fill::FILL_NONE, $sheet->getStyle('A3')->getFill()->getFillType());
        $this->assertEquals(Fill::FILL_NONE, $sheet->getStyle('A4')->getFill()->getFillType());
        $this->assertEquals(Fill::FILL_NONE, $sheet->getStyle('A5')->getFill()->getFillType());
    }

    public function test_attendance_export_can_hide_status_column()
    {
        $shift = Shift::create([
            'shift_name' => 'Pagi',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
        ]);
        $attendedUser = User::factory()->create(['name' => 'Budi Santoso', 'is_active' => true]);
        $today = Carbon::today();
        $att = Attendance::create([
            'user_id' => $attendedUser->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
        ]);
        AttendanceEvent::create([
            'attendance_id' => $att->id,
            'event_type' => EventType::START_SHIFT,
            'timestamp' => $today->copy()->setTime(8, 0, 0),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie_path' => 'selfies/test.jpg',
        ]);

        $export = new AttendanceExport($today->toDateString(), $today->toDateString(), null, null, null, false, false);
        $headings = $export->headings();

        $this->assertNotContains('Status', $headings);
        $this->assertCount(9, $headings);

        $mapped = $export->map($att);
        $this->assertCount(9, $mapped);

        // Test styles with hidden status column
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headings, $mapped]);
        $export->styles($sheet);

        $this->assertEquals('I', $sheet->getHighestColumn());
        $this->assertEquals('FFFFE599', $sheet->getStyle('A1')->getFill()->getStartColor()->getARGB());
        $this->assertEquals(28, $sheet->getRowDimension(1)->getRowHeight());
        $this->assertEquals(22, $sheet->getRowDimension(2)->getRowHeight());
    }

    public function test_break_export_can_hide_status_column()
    {
        $shift = Shift::create([
            'shift_name' => 'Pagi',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
        ]);
        $user = User::factory()->create(['name' => 'Budi Istirahat', 'is_active' => true]);
        $today = Carbon::today();
        $att = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => $today->toDateString(),
        ]);

        $export = new BreakExport($today->toDateString(), $today->toDateString(), null, null, null, false, false);
        $headings = $export->headings();

        $this->assertNotContains('Status', $headings);
        $this->assertCount(10, $headings);

        $mapped = $export->map($att);
        $this->assertCount(10, $mapped);

        // Test styles with hidden status column
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headings, $mapped]);
        $export->styles($sheet);

        $this->assertEquals('J', $sheet->getHighestColumn());
        $this->assertEquals('FFFFE599', $sheet->getStyle('A1')->getFill()->getStartColor()->getARGB());
        $this->assertEquals(28, $sheet->getRowDimension(1)->getRowHeight());
        $this->assertEquals(22, $sheet->getRowDimension(2)->getRowHeight());
    }

    public function test_export_controllers_handle_hide_status_option()
    {
        Excel::fake();

        $hr = User::factory()->create();
        $hr->assignRole('HR');

        // Attendance with hide_status = 1
        $this->actingAs($hr)->get(route('admin.attendances.export', ['export_type' => 'today', 'hide_status' => '1']))
            ->assertStatus(200);

        Excel::assertDownloaded('attendance_'.date('Y-m-d_H-i-s').'.xlsx', function (AttendanceExport $export) {
            $reflector = new \ReflectionClass($export);
            $prop = $reflector->getProperty('showStatus');
            $prop->setAccessible(true);

            return $prop->getValue($export) === false;
        });

        // Attendance with export_type = all and hide_status = 1
        $this->actingAs($hr)->get(route('admin.attendances.export', [
            'include_all_employees' => '1',
            'export_type' => 'all',
            'search' => '',
            'date' => '',
            'shift_id' => '',
            'location_id' => '',
            'start_date' => '',
            'end_date' => '',
            'hide_status' => '1',
        ]))->assertStatus(200);

        // Break with hide_status = 1
        $this->actingAs($hr)->get(route('admin.breaks.export', ['export_type' => 'today', 'hide_status' => '1']))
            ->assertStatus(200);

        Excel::assertDownloaded('break_history_'.date('Y-m-d_H-i-s').'.xlsx', function (BreakExport $export) {
            $reflector = new \ReflectionClass($export);
            $prop = $reflector->getProperty('showStatus');
            $prop->setAccessible(true);

            return $prop->getValue($export) === false;
        });

        // Attendance with color_coded = 1 and show_status = 1
        $this->actingAs($hr)->get(route('admin.attendances.export', ['export_type' => 'today', 'color_coded' => '1']))
            ->assertStatus(200);

        Excel::assertDownloaded('attendance_'.date('Y-m-d_H-i-s').'.xlsx', function (AttendanceExport $export) {
            $reflector = new \ReflectionClass($export);
            $propStatus = $reflector->getProperty('showStatus');
            $propStatus->setAccessible(true);
            $propColor = $reflector->getProperty('colorCoded');
            $propColor->setAccessible(true);

            return $propStatus->getValue($export) === true && $propColor->getValue($export) === true;
        });

        // Attendance with color_coded = 1 but hide_status = 1 (should force colorCoded to false)
        $this->actingAs($hr)->get(route('admin.attendances.export', ['export_type' => 'today', 'color_coded' => '1', 'hide_status' => '1']))
            ->assertStatus(200);

        Excel::assertDownloaded('attendance_'.date('Y-m-d_H-i-s').'.xlsx', function (AttendanceExport $export) {
            $reflector = new \ReflectionClass($export);
            $propColor = $reflector->getProperty('colorCoded');
            $propColor->setAccessible(true);

            return $propColor->getValue($export) === false;
        });
    }

    public function test_attendance_and_break_export_can_apply_color_coding_when_requested()
    {
        // Attendance with color-coding enabled
        $export = new AttendanceExport(null, null, null, null, null, true, true, true);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            $export->headings(),
            [1, 'EMP01', '3201', 'Budi Santoso', 'Kantor', '2026-11-16', 'Pagi', '08:00:00', '17:00:00', 'Hadir'],
            [2, 'EMP02', '3202', 'Siti Mangkir', 'Kantor', '2026-11-16', 'Pagi', '-', '-', 'Mangkir / Belum Presensi'],
            [3, 'EMP03', '3203', 'Agus Lupa', 'Kantor', '2026-11-16', 'Pagi', '08:00:00', '-', 'Hadir (Lupa Checkout)'],
        ]);
        $export->styles($sheet);

        $this->assertEquals('FFFFE2E2', $sheet->getStyle('A3')->getFill()->getStartColor()->getARGB());
        $this->assertEquals('FFFFF3C7', $sheet->getStyle('A4')->getFill()->getStartColor()->getARGB());

        // Break with color-coding enabled
        $exportBreak = new BreakExport(null, null, null, null, null, true, true, true);
        $spreadsheetBreak = new Spreadsheet;
        $sheetBreak = $spreadsheetBreak->getActiveSheet();
        $sheetBreak->fromArray([
            $exportBreak->headings(),
            [1, 'EMP01', '3201', 'Budi Istirahat', 'Kantor', '2026-11-16', 'Pagi', '12:00:00', '12:45:00', '00:45:00', 'Tepat Waktu'],
            [2, 'EMP02', '3202', 'Siti Mangkir', 'Kantor', '2026-11-16', 'Pagi', '-', '-', '-', 'Mangkir / Belum Presensi'],
            [3, 'EMP03', '3203', 'Doni NoBreak', 'Kantor', '2026-11-16', 'Pagi', '-', '-', '-', 'Tidak Istirahat'],
            [4, 'EMP04', '3204', 'Agus Sedang', 'Kantor', '2026-11-16', 'Pagi', '12:00:00', '-', '-', 'Sedang Istirahat'],
        ]);
        $exportBreak->styles($sheetBreak);

        $this->assertEquals('FFFFE2E2', $sheetBreak->getStyle('A3')->getFill()->getStartColor()->getARGB());
        $this->assertEquals('FFF8FAFC', $sheetBreak->getStyle('A4')->getFill()->getStartColor()->getARGB());
        $this->assertEquals('FFFFF3C7', $sheetBreak->getStyle('A5')->getFill()->getStartColor()->getARGB());
    }
}
