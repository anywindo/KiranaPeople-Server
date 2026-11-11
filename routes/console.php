<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('employees:sync-birthdays', function () {
    $users = \App\Models\User::all();
    $dobUpdated = 0;
    $passwordUpdated = 0;
    $empIdUpdated = 0;

    foreach ($users as $user) {
        $dirty = false;

        // Populate employee_id if missing
        if (empty($user->getRawOriginal('employee_id'))) {
            $user->employee_id = 'EMP-'.str_pad($user->id, 5, '0', STR_PAD_LEFT);
            $dirty = true;
            $empIdUpdated++;
        }

        // Populate dob from NIK if missing
        if (! $user->dob && $user->nik) {
            $dob = \App\Models\User::parseDobDateFromNik($user->nik);
            if ($dob) {
                $user->dob = $dob;
                $dirty = true;
                $dobUpdated++;
            }
        }

        // Only check password if user has a valid birthday (avoid slow Hash::check on unrelated users)
        $birthdayPwd = $user->getDefaultPassword();
        if ($birthdayPwd !== 'password') {
            if (\Illuminate\Support\Facades\Hash::check('password', $user->password)) {
                $user->password = \Illuminate\Support\Facades\Hash::make($birthdayPwd);
                $dirty = true;
                $passwordUpdated++;
            }
        }

        if ($dirty) {
            $user->save();
        }
    }

    $this->info("Updated Employee ID for {$empIdUpdated} users.");
    $this->info("Updated DOB for {$dobUpdated} users.");
    $this->info("Updated password to birthday DDMMYYYY for {$passwordUpdated} users.");
})->purpose('Populate missing Employee ID and DOB, and migrate default "password" to birthday DDMMYYYY');
