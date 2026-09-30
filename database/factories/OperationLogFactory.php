<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationLog>
 */
class OperationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'user_role' => UserRole::PtoAdministrator->value,
            'action' => 'update',
            'entity_type' => 'establishment',
            'entity_id' => fake()->numberBetween(1, 1000),
            'municipality_id' => null,
            'establishment_id' => null,
            'old_values' => null,
            'new_values' => null,
            'reason' => null,
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
