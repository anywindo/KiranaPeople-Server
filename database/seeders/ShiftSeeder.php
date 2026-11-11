<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        // Standard Morning Shift
        Shift::factory()->create([
            'shift_name' => 'Morning Shift',
            'default_start_time' => '08:00:00',
            'default_end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        // Standard Evening Shift
        Shift::factory()->create([
            'shift_name' => 'Evening Shift',
            'default_start_time' => '15:00:00',
            'default_end_time' => '23:00:00',
            'break_start' => '18:00:00',
            'break_end' => '19:00:00',
        ]);

        // Night Shift
        Shift::factory()->create([
            'shift_name' => 'Night Shift',
            'default_start_time' => '23:00:00',
            'default_end_time' => '07:00:00',
            'break_start' => '03:00:00',
            'break_end' => '04:00:00',
        ]);
    }
}
