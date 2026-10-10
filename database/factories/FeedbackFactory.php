<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Factory — tourist feedback.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lst_id' => Listing::query()->first()?->lst_id,
            'fbk_name' => fake()->firstName(),
            'fbk_rating' => 5,
            'fbk_text' => 'Beautiful place and very friendly staff.',
            'fbk_language' => 'English',
            'fbk_sentiment' => 'Positive',
            'fbk_polarity' => 0.8,
        ];
    }

    public function negative(): static
    {
        return $this->state(fn () => [
            'fbk_rating' => 1,
            'fbk_text' => 'Dirty and the staff were rude.',
            'fbk_sentiment' => 'Negative',
            'fbk_polarity' => -0.8,
        ]);
    }
}
