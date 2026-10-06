<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Model factory for generating operation log test records.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

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
            'usr_id' => User::factory(),
            'opl_user_role' => UserRole::PtoAdministrator->value,
            'opl_action' => 'update',
            'opl_entity_type' => 'establishment',
            'opl_entity_id' => fake()->numberBetween(1, 1000),
            'mun_id' => null,
            'lst_id' => null,
            'opl_old_values' => null,
            'opl_new_values' => null,
            'opl_reason' => null,
            'opl_ip_address' => fake()->ipv4(),
            'opl_created_at' => now(),
        ];
    }
}
