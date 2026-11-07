<?php

namespace Database\Factories;

use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Models\Crane;
use App\Models\Fault;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fault>
 */
class FaultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crane_id' => Crane::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'severity' => FaultSeverity::Medium,
            'status' => FaultStatus::Open,
            'reported_by' => User::factory(),
            'reported_at' => now(),
        ];
    }
}
