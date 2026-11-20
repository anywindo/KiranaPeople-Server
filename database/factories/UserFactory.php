<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dob = fake()->dateTimeBetween('-45 years', '-20 years');
        $dobFormatted = $dob->format('Y-m-d');

        return [
            'name' => fake('id_ID')->name(),
            'email' => fake('id_ID')->unique()->safeEmail(),
            'employee_id' => fake('id_ID')->unique()->numerify('EMP-#####'),
            'nik' => fake('id_ID')->unique()->numerify('################'),
            'dob' => $dobFormatted,
            'phone' => fake('id_ID')->phoneNumber(),
            'location_id' => Location::inRandomOrder()->first()?->id ?? Location::factory(),
            'avatar' => null, // Factory won't generate real image files, we'll leave it null
            'is_active' => true,
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user has not changed their default password.
     */
    public function needsPasswordChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_changed_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
