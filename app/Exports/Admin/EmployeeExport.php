<?php

namespace App\Exports\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeeExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    protected $rowNumber = 0;

    public function query(): Builder|\Illuminate\Database\Query\Builder
    {
        return User::with(['location', 'roles'])->orderBy('name', 'asc');
    }

    public function headings(): array
    {
        return [
            'No.',
            'ID Karyawan',
            'NIK',
            'Tanggal Lahir',
            'Nama Lengkap',
            'Email',
            'Lokasi Kerja',
            'Status',
            'Role',
        ];
    }

    public function map($employee): array
    {
        $this->rowNumber++;

        $roles = $employee->roles->pluck('name')->join(', ');

        return [
            $this->rowNumber,
            $employee->employee_id,
            $employee->nik ?? '-',
            $employee->dob ? $employee->dob->format('Y-m-d') : ($employee->nik ? (\App\Models\User::parseDobDateFromNik($employee->nik) ?? '-') : '-'),
            $employee->name,
            $employee->email,
            $employee->location->name ?? '-',
            $employee->is_active ? 'Aktif' : 'Nonaktif',
            $roles ?: 'Karyawan',
        ];
    }
}
