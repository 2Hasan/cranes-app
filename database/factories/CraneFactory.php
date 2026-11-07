<?php

namespace Database\Factories;

use App\Enums\CraneStatus;
use App\Models\Crane;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crane>
 */
class CraneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'R-'.fake()->unique()->numerify('###'),
            'name' => fake()->word(),
            'type' => 'رافعة برجية',
            'capacity_ton' => fake()->randomElement([12, 20, 50]),
            'serial_number' => fake()->bothify('SN-####-????'),
            'manufacture_year' => fake()->numberBetween(2010, 2024),
            'site_id' => Site::factory(),
            'status' => CraneStatus::Working,
            'current_km' => 0,
            'service_interval_days' => 90,
            'service_interval_km' => 5000,
            'last_service_date' => now()->subDays(30)->toDateString(),
            'last_service_km' => 0,
        ];
    }

    public function stopped(): static
    {
        return $this->state(fn (): array => ['status' => CraneStatus::Stopped]);
    }
}
