<?php

namespace Database\Factories;

use App\Enums\MaintenanceType;
use App\Models\Crane;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRecord>
 */
class MaintenanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crane_id' => Crane::factory(),
            'technician_name' => fake()->name(),
            'created_by' => User::factory(),
            'type' => MaintenanceType::Periodic,
            'performed_on' => now()->subDays(10)->toDateString(),
            'km' => null,
            'description' => fake()->sentence(),
        ];
    }

    public function repair(): static
    {
        return $this->state(fn (): array => ['type' => MaintenanceType::Repair]);
    }
}
