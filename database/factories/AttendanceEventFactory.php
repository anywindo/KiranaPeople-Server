<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\AttendanceEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceEvent>
 */
class AttendanceEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_type' => EventType::START_SHIFT,
            'timestamp' => now(),
            'latitude' => $this->faker->latitude(-6.2, -6.1), // Near Jakarta
            'longitude' => $this->faker->longitude(106.8, 106.9),
            'selfie_path' => 'selfies/dummy.jpg',
        ];
    }
}
