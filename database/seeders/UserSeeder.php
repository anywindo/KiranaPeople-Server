<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('password');
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
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'employee_id' => 'SA-00001',
                'nik' => '1111222233334444',
                'dob' => '1985-01-01',
                'phone' => '081234567890',
                'password' => $defaultPassword,
                'avatar' => 'avatars/6acd9bef6c89c_1791859695.webp',
                'location_id' => $locationId,
                'is_active' => true,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );
        $superadmin->roles()->syncWithoutDetaching([$superAdminRole->id]);

        // Create an HR Admin
        $hr = User::firstOrCreate(
            ['email' => 'hr@example.com'],
            [
                'name' => 'HR Admin',
                'employee_id' => 'HR-00001',
                'nik' => '5555666677778888',
                'dob' => '1990-01-01',
                'phone' => '081234567891',
                'password' => $defaultPassword,
                'avatar' => 'avatars/6ac71a0a19855_1791433226.png',
                'location_id' => $locationId,
                'is_active' => true,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );
        $hr->roles()->syncWithoutDetaching([$hrRole->id]);

        $seedCounter = 1;
        $createWithDobPassword = function (array $attributes = []) use ($locationId, $employeeRole, $avatarList, &$avatarIndex, &$seedCounter) {
            $dobString = sprintf('19%02d-%02d-%02d', rand(80, 99), rand(1, 12), rand(1, 28));
            $dobDateTime = \DateTime::createFromFormat('Y-m-d', $dobString);
            $dobFormatted = $dobDateTime ? $dobDateTime->format('Y-m-d') : '1992-05-15';
            $dobPassword = $dobDateTime ? $dobDateTime->format('dmY') : '15051992';

            $defaultAttributes = [
                'name' => 'Karyawan ' . $seedCounter,
                'email' => 'karyawan' . $seedCounter . '@example.com',
                'employee_id' => sprintf('EMP-%05d', $seedCounter + 100),
                'nik' => sprintf('3201%012d', $seedCounter),
                'phone' => sprintf('08123456%04d', $seedCounter),
                'dob' => $dobFormatted,
                'password' => Hash::make($dobPassword),
                'password_changed_at' => null,
                'location_id' => $locationId,
                'avatar' => $avatarList[$avatarIndex++ % count($avatarList)],
                'is_active' => true,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ];

            // If Faker is available, use it for nicer names
            if (function_exists('fake')) {
                try {
                    $faker = fake('id_ID');
                    $defaultAttributes['name'] = $faker->name();
                    $defaultAttributes['email'] = $faker->unique()->safeEmail();
                    $defaultAttributes['phone'] = $faker->phoneNumber();
                } catch (\Throwable $e) {
                }
            }

            $user = User::create(array_merge($defaultAttributes, $attributes));
            $user->roles()->syncWithoutDetaching([$employeeRole->id]);
            $seedCounter++;

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
                'location_id' => $manager->location_id,
            ]));
        }

        // Create an Employee (for testing)
        $employee = User::firstOrCreate(
            ['email' => 'employee@example.com'],
            [
                'name' => 'John Employee',
                'employee_id' => 'EMP-00001',
                'nik' => '9999000011112222',
                'dob' => '2000-01-01',
                'phone' => '081234567899',
                'password' => $defaultPassword,
                'avatar' => 'avatars/6ac7594a71d64_1791449418.webp',
                'location_id' => $locationId,
                'is_active' => true,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );
        $employee->roles()->syncWithoutDetaching([$employeeRole->id]);

        // Create random employees (2 per supervisor = 4 total)
        foreach ($supervisors as $supervisor) {
            for ($i = 0; $i < 2; $i++) {
                $createWithDobPassword([
                    'location_id' => $supervisor->location_id,
                ]);
            }
        }
    }
}
