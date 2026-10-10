<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Seeds the recurring-issue keyword and phrase list
 * (tbl_feedback_issue_lexicons) that maps negative tourist feedback to an
 * issue category. Idempotent: re-running updates keywords in place.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Seeders;

use App\Models\FeedbackIssueLexicon;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * Category names must be keys of config('tourist_feedback.issue_categories').
 * Multi-word phrases are matched as phrases by the analysis service; each
 * keyword belongs to exactly one category.
 */
class FeedbackIssueLexiconSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private const KEYWORDS = [
        'Cleanliness' => ['dirty', 'trash', 'garbage', 'litter', 'littered', 'filthy', 'smelly', 'unsanitary', 'waste'],
        'Maintenance' => ['broken', 'damaged', 'unmaintained', 'poorly maintained', 'poor maintenance', 'dilapidated', 'rusty', 'leaking'],
        'Crowd Management' => ['crowded', 'overcrowded', 'too many people', 'too crowded', 'long line', 'long queue', 'congested'],
        'Facilities' => ['no restroom', 'no toilet', 'no comfort room', 'no shower', 'no parking', 'lack of facilities', 'limited facilities'],
        'Pricing' => ['expensive', 'overpriced', 'costly', 'pricey', 'hidden charges'],
        'Customer Service' => ['rude', 'unfriendly', 'unhelpful', 'unprofessional', 'poor service', 'bad service', 'slow service'],
        'Accessibility' => ['difficult to access', 'inaccessible', 'hard to reach', 'difficult to reach', 'no ramp'],
        'Safety' => ['unsafe', 'dangerous', 'risky', 'slippery', 'no lifeguard', 'no life vest'],
        'Transportation' => ['bad road', 'rough road', 'potholes', 'no transportation', 'no public transport', 'limited transportation'],
        'Information' => ['no signage', 'no signs', 'no information', 'lack of information', 'unclear directions', 'no visiting hours'],
        'Environment' => ['polluted', 'pollution', 'dead coral', 'dead corals', 'damaged coral', 'plastic waste', 'murky'],
    ];

    public function run(): void
    {
        $arrKnownCategories = array_keys(config('tourist_feedback.issue_categories'));

        foreach (self::KEYWORDS as $strCategory => $arrKeywords) {
            // A typo in a category name must fail loudly, never seed an
            // orphan category that has no recommendation.
            if (! in_array($strCategory, $arrKnownCategories, true)) {
                throw new InvalidArgumentException("Unknown feedback issue category: {$strCategory}");
            }

            foreach ($arrKeywords as $strKeyword) {
                FeedbackIssueLexicon::query()->updateOrCreate(
                    ['fil_keyword' => $strKeyword],
                    ['fil_issue_category' => $strCategory, 'fil_weight' => 1]
                );
            }
        }

        FeedbackIssueLexicon::clearCachedLexicon();
    }
}
