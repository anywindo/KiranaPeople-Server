<?php

namespace App\Exports\Admin;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BreakExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    protected $rowNumber = 0;

    protected $startDate;

    protected $endDate;

    protected $search;

    protected $shift_id;

    protected $location_id;

    protected $includeAll;

    public function __construct(
        $startDate = null,
        $endDate = null,
        $search = null,
        $shift_id = null,
        $location_id = null,
        $includeAll = true
    ) {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;
        $this->shift_id = $shift_id;
        $this->location_id = $location_id;
        $this->includeAll = (bool) $includeAll;
    }

    public function collection(): \Illuminate\Support\Collection
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

        // When only exporting employees who took a break
        if (! $this->includeAll) {
            return $query->whereHas('events', function ($q) {
                $q->whereIn('event_type', ['START_BREAK', 'END_BREAK']);
            })->get();
        }

        $attendances = $query->get();

        $excludedRoles = json_decode(Setting::get('exclude_roles_from_attendance', '["super-admin"]'), true);
        if (! is_array($excludedRoles)) {
            $excludedRoles = ['super-admin'];
        }

        $userQuery = User::active()
            ->with(['location'])
            ->whereDoesntHave('roles', function ($q) use ($excludedRoles) {
                $q->whereIn('name', $excludedRoles);
            });

        if ($this->search) {
            $search = $this->search;
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($this->location_id) {
            $userQuery->where('location_id', $this->location_id);
        }

        $eligibleUsers = $userQuery->orderBy('name', 'asc')->get();

        // Determine list of dates
        $dates = collect();
        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate);
            $end = Carbon::parse($this->endDate);
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
            }
            $period = CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                $dates->push($date->toDateString());
            }
            $dates = $dates->reverse()->values();
        } elseif ($this->startDate) {
            $dates->push(Carbon::parse($this->startDate)->toDateString());
        } elseif ($this->endDate) {
            $dates->push(Carbon::parse($this->endDate)->toDateString());
        } else {
            $distinctDates = Attendance::distinct()
                ->orderBy('date', 'desc')
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->values();

            if ($distinctDates->isNotEmpty()) {
                $dates = $distinctDates;
            } else {
                $dates->push(Carbon::today()->toDateString());
            }
        }

        $attendancesByDate = $attendances->groupBy(function ($att) {
            return Carbon::parse($att->date)->toDateString();
        });

        $defaultShift = Shift::first();
        $targetShift = $this->shift_id ? Shift::find($this->shift_id) : null;

        $results = collect();

        foreach ($dates as $dateStr) {
            $dayAttendances = $attendancesByDate->get($dateStr, collect());
            $attendedUserIds = $dayAttendances->pluck('user_id')->all();

            foreach ($dayAttendances as $att) {
                $results->push($att);
            }

            foreach ($eligibleUsers as $user) {
                if (in_array($user->id, $attendedUserIds)) {
                    continue;
                }

                $userShift = $user->shift ?? $defaultShift;
                if ($this->shift_id && ($userShift?->id != $this->shift_id)) {
                    continue;
                }

                $results->push((object) [
                    'is_absent' => true,
                    'user' => $user,
                    'date' => Carbon::parse($dateStr),
                    'shift' => $targetShift ?? $userShift,
                ]);
            }
        }

        return $results;
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

        if (isset($attendance->is_absent) && $attendance->is_absent) {
            return [
                $this->rowNumber,
                $attendance->user->employee_id ?? '-',
                $attendance->user->nik ?? '-',
                $attendance->user->name ?? '-',
                $attendance->user->location->name ?? '-',
                $attendance->date ? $attendance->date->format('Y-m-d') : '-',
                $attendance->shift ? $attendance->shift->shift_name : '-',
                '-',
                '-',
                '-',
                'Mangkir / Belum Presensi',
            ];
        }

        $startBreak = $attendance->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'START_BREAK');
        $endBreak = $attendance->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'END_BREAK');

        $duration = '-';
        if ($startBreak && $endBreak) {
            $diff = Carbon::parse($endBreak->timestamp)->diff(Carbon::parse($startBreak->timestamp));
            $duration = sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s);
        }

        if ($startBreak || $endBreak) {
            $status = is_array($attendance->break_status) ? ($attendance->break_status['label'] ?? '-') : ($attendance->break_status ?? '-');
        } else {
            $status = 'Tidak Istirahat';
        }

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

