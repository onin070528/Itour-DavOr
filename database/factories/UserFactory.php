<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Model factory for generating user test records.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Factories;

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
        return [
            'usr_name' => fake()->name(),
            'usr_email' => fake()->unique()->safeEmail(),
            'usr_email_verified_at' => now(),
            'usr_password' => static::$password ??= Hash::make('password'),
            'usr_remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $arrAttributes) => [
            'usr_email_verified_at' => null,
        ]);
    }
}
