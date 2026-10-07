<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Model factory for generating security log test records.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Factories;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityLog>
 */
class SecurityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sec_event_type' => 'login_success',
            'usr_id' => User::factory(),
            'sec_attempted_email' => null,
            'sec_target_user_id' => null,
            'mun_id' => null,
            'sec_ip_address' => fake()->ipv4(),
            'sec_user_agent' => fake()->userAgent(),
            'sec_details' => null,
            'sec_created_at' => now(),
        ];
    }
}
