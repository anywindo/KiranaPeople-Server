<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\EmployeeLateNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminApiAndQueueWorkerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee1;
    protected User $employee2;
    protected Location $location;
    protected Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'Employee']);

        $this->location = Location::create([
            'name' => 'Main Office',
            'latitude' => -6.208800,
            'longitude' => 106.845600,
            'radius' => 100,
        ]);

        $this->shift = Shift::create([
            'shift_name' => 'Morning Shift',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $this->admin = User::factory()->create([
            'password_changed_at' => now(),
            'location_id' => $this->location->id,
        ]);
        $this->admin->assignRole('super-admin');

        $this->employee1 = User::factory()->create([
            'name' => 'Alice Tan',
            'email' => 'alice@example.com',
            'nik' => '3201010101010001',
            'employee_id' => 'EMP-00101',
            'phone' => '081234567891',
            'location_id' => $this->location->id,
            'password_changed_at' => now(),
            'is_active' => true,
        ]);
        $this->employee1->assignRole('Employee');

        $this->employee2 = User::factory()->create([
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'nik' => '3201010101010002',
            'employee_id' => 'EMP-00102',
            'phone' => '081234567892',
            'location_id' => $this->location->id,
            'password_changed_at' => now(),
            'is_active' => false,
        ]);
        $this->employee2->assignRole('Employee');
    }

    public function test_employee_late_notification_implements_should_queue(): void
    {
        $notification = new EmployeeLateNotification($this->employee1, 15);
        $this->assertInstanceOf(ShouldQueue::class, $notification);

        $channels = $notification->via($this->admin);
        $this->assertContains('database', $channels);

        $arrayData = $notification->toArray($this->admin);
        $this->assertStringContainsString('Alice Tan terlambat 15 menit', $arrayData['message']);
        $this->assertEquals(15, $arrayData['lateness_minutes']);
    }

    public function test_admin_can_list_employees_with_pagination_and_search(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/employees?search=Alice');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'employee_id',
                    'nik',
                    'location',
                    'roles',
                ]
            ],
            'links',
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ]
        ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Alice Tan', $data[0]['name']);
    }

    public function test_admin_can_filter_employees_by_active_status(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/employees?is_active=0');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Bob Smith', $data[0]['name']);
    }

    public function test_admin_can_list_attendances_with_eager_loading_and_filter(): void
    {
        $attendance = Attendance::create([
            'user_id' => $this->employee1->id,
            'shift_id' => $this->shift->id,
            'date' => now()->toDateString(),
            'status' => 'EXACT',
        ]);

        AttendanceEvent::create([
            'attendance_id' => $attendance->id,
            'event_type' => 'START_SHIFT',
            'timestamp' => now()->setTime(8, 0, 0),
            'latitude' => -6.208800,
            'longitude' => 106.845600,
            'selfie_path' => 'selfies/alice.jpg',
            'accuracy' => 15.0,
            'notes' => 'Tepat waktu',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/attendances?search=Alice');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'shift_id',
                    'date',
                    'status',
                    'shift_status',
                    'break_status',
                    'events',
                    'shift',
                ]
            ],
            'links',
            'meta'
        ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertNotNull($data[0]['shift']);
        $this->assertNotEmpty($data[0]['events']);
        $this->assertEquals('Tepat waktu', $data[0]['events'][0]['notes']);
    }
}
