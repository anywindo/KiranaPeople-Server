<?php

namespace App\Repositories\Eloquent;

use App\Models\Attendance;
use App\Repositories\Contracts\AttendanceRepositoryInterface;

class AttendanceRepository extends BaseRepository implements AttendanceRepositoryInterface
{
    protected string $model = Attendance::class;

    /**
     * Find today's attendance for a user.
     */
    public function findTodayAttendanceForUser(int $userId, bool $lockForUpdate = false)
    {
        $query = $this->newQuery()
            ->where('user_id', $userId)
            ->whereDate('date', today());

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
