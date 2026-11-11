<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case EARLY = 'EARLY';
    case EXACT = 'EXACT';
    case LATE = 'LATE';
    case ABSENT = 'ABSENT';
    case ON_LEAVE = 'ON_LEAVE';

    public function label(): string
    {
        return match ($this) {
            self::EARLY => 'Lebih Awal',
            self::EXACT => 'Tepat Waktu',
            self::LATE => 'Terlambat',
            self::ABSENT => 'Tidak Hadir',
            self::ON_LEAVE => 'Cuti',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EARLY => 'info',
            self::EXACT => 'success',
            self::LATE => 'danger',
            self::ABSENT => 'secondary',
            self::ON_LEAVE => 'warning',
        };
    }
}
