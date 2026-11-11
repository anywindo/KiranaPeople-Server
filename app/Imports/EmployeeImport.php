<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeeImport implements ToModel, WithHeadingRow
{
    public function model(array $row): ?Model
    {
        $dob = null;
        if (! empty($row['dob'])) {
            $dob = $row['dob'];
        } elseif (! empty($row['birth_date'])) {
            $dob = $row['birth_date'];
        } elseif (! empty($row['tanggal_lahir'])) {
            $dob = $row['tanggal_lahir'];
        }

        if ($dob && is_numeric($dob)) {
            try {
                $dob = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dob)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        } elseif ($dob) {
            try {
                $dob = \Illuminate\Support\Carbon::parse($dob)->format('Y-m-d');
            } catch (\Throwable $e) {
                $dob = null;
            }
        }

        if (! $dob && ! empty($row['nik'])) {
            $dob = User::parseDobDateFromNik($row['nik']);
        }

        if (isset($row['password']) && filled($row['password'])) {
            $password = Hash::make($row['password']);
        } else {
            $defaultPwd = User::computeDefaultPassword($dob, $row['nik'] ?? null);
            $password = Hash::make($defaultPwd);
        }

        return new User([
            'employee_id' => $row['employee_id'] ?? null,
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? null,
            'nik' => $row['nik'] ?? null,
            'dob' => $dob,
            'password' => $password,
            'is_active' => true,
        ]);
    }
}
