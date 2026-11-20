<?php

namespace App\Exports;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder|\Illuminate\Database\Eloquent\Builder|Relation
    {
        // For larger datasets, returning a query is better than all()
        return Attendance::query()->with('user', 'shift', 'events');
    }

    public function map($attendance): array
    {
        return [
            $attendance->id,
            $attendance->user->name ?? 'Unknown',
            $attendance->shift->shift_name ?? 'Unknown',
            $attendance->date,
            $attendance->status,
        ];
    }

    public function headings(): array
    {
        return [
            'Attendance ID',
            'Employee Name',
            'Shift',
            'Date',
            'Status',
        ];
    }
}
