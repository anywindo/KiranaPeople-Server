<?php

namespace App\Exports\Admin;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithChunkReading
{
    public function chunkSize(): int
    {
        return 500;
    }

    protected $rowNumber = 0;

    protected $startDate;

    protected $endDate;

    protected $search;

    protected $shift_id;

    protected $location_id;

    public function __construct($startDate = null, $endDate = null, $search = null, $shift_id = null, $location_id = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;
        $this->shift_id = $shift_id;
        $this->location_id = $location_id;
    }

    public function query(): Builder|\Illuminate\Database\Query\Builder
    {
        $query = Attendance::with(['user.location', 'shift', 'events'])->orderBy('date', 'desc');

        if ($this->startDate) {
            $query->whereDate('date', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('date', '<=', $this->endDate);
        }

        if ($this->search) {
            $search = $this->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($this->shift_id) {
            $query->where('shift_id', $this->shift_id);
        }

        if ($this->location_id) {
            $location_id = $this->location_id;
            $query->whereHas('user', function ($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'No.',
            'ID Karyawan',
            'NIK (KTP)',
            'Nama karyawan',
            'Nama Lokasi Kerja',
            'Tanggal',
            'Shift',
            'Masuk Aktual',
            'Keluar Aktual',
            'Status',
        ];
    }

    public function map($attendance): array
    {
        $this->rowNumber++;

        $checkInEvent = $attendance->events->where('event_type', \App\Enums\EventType::START_SHIFT)->first();
        $checkOutEvent = $attendance->events->where('event_type', \App\Enums\EventType::END_SHIFT)->first();

        $status = is_array($attendance->shift_status) ? ($attendance->shift_status['label'] ?? '-') : ($attendance->shift_status ?? '-');

        return [
            $this->rowNumber,
            $attendance->user->employee_id ?? '-',
            $attendance->user->nik ?? '-',
            $attendance->user->name ?? '-',
            $attendance->user->location->name ?? '-',
            $attendance->date ? $attendance->date->format('Y-m-d') : '-',
            $attendance->shift ? $attendance->shift->shift_name : '-',
            $checkInEvent ? $checkInEvent->timestamp->format('H:i:s') : '-',
            $checkOutEvent ? $checkOutEvent->timestamp->format('H:i:s') : '-',
            $status,
        ];
    }
}
