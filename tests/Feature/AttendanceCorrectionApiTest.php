<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceCorrectionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private Attendance $attendanceA;

    private Attendance $attendanceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();

        $shift = Shift::create([
            'shift_name' => 'General Shift',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
        ]);

        $this->attendanceA = Attendance::create([
            'user_id' => $this->userA->id,
            'shift_id' => $shift->id,
            'date' => today(),
            'status' => AttendanceStatus::EXACT,
        ]);

        $this->attendanceB = Attendance::create([
            'user_id' => $this->userB->id,
            'shift_id' => $shift->id,
            'date' => today(),
            'status' => AttendanceStatus::EXACT,
        ]);
    }

    public function test_employee_can_submit_correction_request_for_own_attendance(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/v1/employee/correction-requests', [
            'attendance_id' => $this->attendanceA->id,
            'reason' => 'Forgot to check out before leaving',
            'requested_changes' => [
                'END_SHIFT' => '17:05:00',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.attendance_id', $this->attendanceA->id)
            ->assertJsonPath('data.reason', 'Forgot to check out before leaving');

        $this->assertDatabaseHas('attendance_correction_requests', [
            'user_id' => $this->userA->id,
            'attendance_id' => $this->attendanceA->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_bola_prevented_employee_cannot_submit_correction_for_another_users_attendance(): void
    {
        Sanctum::actingAs($this->userA);

        // User A attempts to target User B's attendance
        $response = $this->postJson('/api/v1/employee/correction-requests', [
            'attendance_id' => $this->attendanceB->id,
            'reason' => 'Malicious or accidental tamper attempt',
            'requested_changes' => [
                'START_SHIFT' => '08:00:00',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attendance_id']);

        $this->assertDatabaseMissing('attendance_correction_requests', [
            'attendance_id' => $this->attendanceB->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_submit_correction_request(): void
    {
        $response = $this->postJson('/api/v1/employee/correction-requests', [
            'attendance_id' => $this->attendanceA->id,
            'reason' => 'Unauthenticated attempt',
            'requested_changes' => [
                'START_SHIFT' => '08:00:00',
            ],
        ]);

        $response->assertStatus(401);
    }
}
