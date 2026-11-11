<?php

namespace App\Repositories\Contracts;

interface AttendanceRepositoryInterface extends RepositoryInterface
{
    /**
     * Find today's attendance for a user.
     */
    public function findTodayAttendanceForUser(int $userId, bool $lockForUpdate = false);
}
