<?php

namespace App\Exports\Admin;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BreakExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithChunkReading
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
        // Get attendances that have any break events
        $query = Attendance::with(['user.location', 'shift', 'events'])
            ->whereHas('events', function ($q) {
                $q->whereIn('event_type', ['START_BREAK', 'END_BREAK']);
            })
            ->orderBy('date', 'desc');

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
            'Mulai Istirahat',
            'Selesai Istirahat',
            'Durasi Istirahat',
            'Status',
        ];
    }

    public function map($attendance): array
    {
        $this->rowNumber++;

        $startBreak = $attendance->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'START_BREAK');
        $endBreak = $attendance->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'END_BREAK');

        $duration = '-';
        if ($startBreak && $endBreak) {
            $diff = Carbon::parse($endBreak->timestamp)->diff(Carbon::parse($startBreak->timestamp));
            $duration = sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s);
        }

        $status = is_array($attendance->break_status) ? ($attendance->break_status['label'] ?? '-') : ($attendance->break_status ?? '-');

        return [
            $this->rowNumber,
            $attendance->user->employee_id ?? '-',
            $attendance->user->nik ?? '-',
            $attendance->user->name ?? '-',
            $attendance->user->location->name ?? '-',
            $attendance->date ? $attendance->date->format('Y-m-d') : '-',
            $attendance->shift ? $attendance->shift->shift_name : '-',
            $startBreak ? $startBreak->timestamp->format('H:i:s') : '-',
            $endBreak ? $endBreak->timestamp->format('H:i:s') : '-',
            $duration,
            $status,
        ];
    }
}
