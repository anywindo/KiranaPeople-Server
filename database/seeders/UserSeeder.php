<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = bcrypt('password');
        $locationId = Location::first()->id ?? null;

        $superAdminRole = Role::findByName('super-admin', 'web');
        $hrRole = Role::findByName('HR', 'web');
        $employeeRole = Role::findByName('Employee', 'web');

        $avatarList = [
            'avatars/6ac7594a71d64_1791449418.webp',
            'avatars/6ac8173b259e1_1791498043.webp',
            'avatars/6acc43f79d89f_1791771639.webp',
            'avatars/6acc442cc52ac_1791771692.webp',
            'avatars/6acc4448c2ef3_1791771720.webp',
            'avatars/Disam4onqJL2q3p8vCA3rB7K4ER5rPGarZuUTUCN.png',
            'avatars/tKl3MbNoWtTIxWvUgqH9eo2fDZNOZpupW7XzuRT3.jpg',
            'avatars/zQ7UuEr6Zy1eFjtUBo2dVrXczTTYt2ZIEezTWN4K.webp',
        ];
        $avatarIndex = 0;

        // Create Superadmin
        $superadmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'employee_id' => 'SA-00001',
            'nik' => '1111222233334444',
            'dob' => '1985-01-01',
            'password' => $defaultPassword,
            'avatar' => 'avatars/6acd9bef6c89c_1791859695.webp',
            'location_id' => $locationId,
        ]);
        $superadmin->roles()->syncWithoutDetaching([$superAdminRole->id]);

        // Create an HR Admin
        $hr = User::factory()->create([
            'name' => 'HR Admin',
            'email' => 'hr@example.com',
            'employee_id' => 'HR-00001',
            'nik' => '5555666677778888',
            'dob' => '1990-01-01',
            'password' => $defaultPassword,
            'avatar' => 'avatars/6ac71a0a19855_1791433226.png',
            'location_id' => $locationId,
        ]);
        $hr->roles()->syncWithoutDetaching([$hrRole->id]);

        $createWithDobPassword = function (array $attributes = []) use ($locationId, $employeeRole, $avatarList, &$avatarIndex) {
            $dob = fake()->dateTimeBetween('-45 years', '-22 years');
            $user = User::factory()->create(array_merge([
                'dob' => $dob->format('Y-m-d'),
                'password' => \Illuminate\Support\Facades\Hash::make($dob->format('dmY')),
                'password_changed_at' => null,
                'location_id' => $locationId,
                'avatar' => $avatarList[$avatarIndex++ % count($avatarList)],
            ], $attributes));
            $user->roles()->syncWithoutDetaching([$employeeRole->id]);

            return $user;
        };

        // Create Managers (2 managers)
        $managers = collect();
        for ($i = 0; $i < 2; $i++) {
            $managers->push($createWithDobPassword());
        }

        // Create Supervisors (1 supervisor per manager = 2 total)
        $supervisors = collect();
        foreach ($managers as $manager) {
            $supervisors->push($createWithDobPassword([
                'immediate_manager_id' => $manager->id,
                'location_id' => $manager->location_id,
            ]));
        }

        // Create an Employee (for testing)
        $employee = User::factory()->create([
            'name' => 'John Employee',
            'email' => 'employee@example.com',
            'employee_id' => 'EMP-00001',
            'nik' => '9999000011112222',
            'dob' => '2000-01-01',
            'password' => $defaultPassword,
            'avatar' => 'avatars/6ac7594a71d64_1791449418.webp',
            'direct_supervisor_id' => $supervisors->first()?->id,
            'immediate_manager_id' => $managers->first()?->id,
            'location_id' => $locationId,
        ]);
        $employee->roles()->syncWithoutDetaching([$employeeRole->id]);

        // Create random employees under supervisors (2 per supervisor = 4 total)
        foreach ($supervisors as $supervisor) {
            for ($i = 0; $i < 2; $i++) {
                $createWithDobPassword([
                    'direct_supervisor_id' => $supervisor->id,
                    'immediate_manager_id' => $supervisor->immediate_manager_id,
                    'location_id' => $supervisor->location_id,
                ]);
            }
        }
    }
}
