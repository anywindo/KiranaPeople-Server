<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Kantor Utama (Tersono, Batang)',
                'latitude' => -7.025800796965979,
                'longitude' => 109.95689371114604,
                'radius' => 50,
            ],
            [
                'name' => 'Branch Office (Bandung)',
                'latitude' => -6.9175,
                'longitude' => 107.6191,
                'radius' => 50,
            ],
            [
                'name' => 'Branch Office (Surabaya)',
                'latitude' => -7.2504,
                'longitude' => 112.7688,
                'radius' => 50,
            ],
        ];

        foreach ($locations as $location) {
            \App\Models\Location::create($location);
        }
    }
}
