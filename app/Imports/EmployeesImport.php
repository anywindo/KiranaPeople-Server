<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeesImport implements ToModel, WithHeadingRow
{
    public function model(array $row): \Illuminate\Database\Eloquent\Model|array|null
    {
        if (! isset($row['employee_id'])) {
            return null;
        }

        $locationId = null;
        if (! empty($row['location'])) {
            $loc = \App\Models\Location::where('name', $row['location'])->first();
            if ($loc) {
                $locationId = $loc->id;
            }
        }

        $user = User::updateOrCreate(
            ['employee_id' => $row['employee_id']],
            [
                'nik' => $row['nik'] ?? null,
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'] ?? null,
                'password' => isset($row['password']) ? Hash::make($row['password']) : Hash::make('password'),
                'location_id' => $locationId,
            ]
        );

        $roleWeb = \Spatie\Permission\Models\Role::findByName('Employee', 'web');
        $user->roles()->syncWithoutDetaching([$roleWeb->id]);

        return $user;
    }
}
