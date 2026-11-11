<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Exempt: HR Admin, Superadmin, and one employee (John Employee: employee@example.com)
        $exemptEmails = [
            'superadmin@example.com',
            'hr@example.com',
            'employee@example.com',
        ];

        $sampleDobs = [
            '1992-05-14',
            '1995-11-20',
            '1990-08-17',
            '1988-03-25',
            '1993-10-12',
            '1996-01-30',
            '1994-07-08',
            '1991-09-18',
            '1997-04-22',
            '1989-12-05',
        ];
        $dobIndex = 0;

        $users = User::all();

        foreach ($users as $user) {
            $email = strtolower(trim((string) $user->email));

            if (in_array($email, $exemptEmails, true)) {
                continue;
            }

            if ($user->hasRole('super-admin') || $user->hasRole('HR')) {
                continue;
            }

            $dob = $user->dob;

            // If user has no DOB, try parsing from NIK or assign from sample list
            if (! $dob && $user->nik) {
                $parsedDob = User::parseDobDateFromNik($user->nik);
                if ($parsedDob) {
                    $dob = Carbon::parse($parsedDob);
                }
            }

            if (! $dob) {
                $dob = Carbon::parse($sampleDobs[$dobIndex++ % count($sampleDobs)]);
            } elseif (! $dob instanceof Carbon) {
                $dob = Carbon::parse($dob);
            }

            $user->dob = $dob->format('Y-m-d');
            // Format password as DDMMYYYY (e.g. 12072004)
            $passwordDmy = $dob->format('dmY');
            $user->password = Hash::make($passwordDmy);
            $user->password_changed_at = null;
            $user->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback necessary for data patch
    }
};
