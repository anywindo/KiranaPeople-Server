<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_endpoint_filters_by_start_and_end_date(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->create();

        // Create October attendance
        Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => '2026-10-15',
            'status' => 'EXACT',
        ]);

        // Create November attendance
        Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-10',
            'status' => 'EXACT',
        ]);

        Sanctum::actingAs($user);

        // Filter October only
        $responseOct = $this->getJson('/api/v1/employee/attendance/history?start_date=2026-10-01&end_date=2026-10-31');
        $responseOct->assertOk();
        $this->assertCount(1, $responseOct->json('data'));
        $this->assertStringStartsWith('2026-10-15', $responseOct->json('data.0.date'));

        // Filter November only
        $responseNov = $this->getJson('/api/v1/employee/attendance/history?start_date=2026-11-01&end_date=2026-11-30');
        $responseNov->assertOk();
        $this->assertCount(1, $responseNov->json('data'));
        $this->assertStringStartsWith('2026-11-10', $responseNov->json('data.0.date'));
    }
}
