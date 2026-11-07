<?php

namespace Database\Factories;

use App\Models\Crane;
use App\Models\KmReading;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KmReading>
 */
class KmReadingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crane_id' => Crane::factory(),
            'recorded_by' => User::factory(),
            'km' => fake()->numberBetween(1000, 100000),
            'recorded_at' => now(),
        ];
    }
}
