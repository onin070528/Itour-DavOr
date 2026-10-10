<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Seeder — demo tourist feedback for existing listings, analyzed with SentimentAnalyzer.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Seeders;

use App\Models\Feedback;
use App\Models\Listing;
use App\Services\SentimentAnalyzer;
use App\Support\PtoMockData;
use Illuminate\Database\Seeder;

/**
 * Loads the demo comments (the same ones the old mock pages showed) for the
 * listings that exist in the database, running each through the real
 * sentiment analyzer. Safe to re-run: it only seeds when the table is empty.
 * Run with: php artisan db:seed --class=FeedbackSeeder
 */
class FeedbackSeeder extends Seeder
{
    public function run(SentimentAnalyzer $objAnalyzer): void
    {
        if (Feedback::query()->exists()) {
            return;
        }

        foreach (PtoMockData::feedback() as $arrRow) {
            $objListing = Listing::query()->where('lst_name', $arrRow['subject'])->first();

            if ($objListing === null) {
                continue;
            }

            // Demo star rating inferred from the sample's own polarity.
            $intRating = match (true) {
                $arrRow['polarity'] >= 0.6 => 5,
                $arrRow['polarity'] >= 0.25 => 4,
                $arrRow['polarity'] > -0.25 => 3,
                $arrRow['polarity'] > -0.6 => 2,
                default => 1,
            };
            $arrAnalysis = $objAnalyzer->analyze($arrRow['text'], $intRating);

            $objFeedback = Feedback::query()->create([
                'lst_id' => $objListing->lst_id,
                'fbk_name' => $arrRow['name'] === 'Anonymous' ? null : $arrRow['name'],
                'fbk_rating' => $intRating,
                'fbk_text' => $arrRow['text'],
                'fbk_language' => $arrAnalysis['language'],
                'fbk_sentiment' => $arrAnalysis['sentiment'],
                'fbk_polarity' => $arrAnalysis['polarity'],
            ]);

            $objFeedback->forceFill(['fbk_created_at' => $arrRow['date'], 'fbk_updated_at' => $arrRow['date']])->saveQuietly();
        }
    }
}
