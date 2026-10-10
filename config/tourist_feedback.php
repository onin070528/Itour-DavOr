<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single configuration source for Objective 4 tourist feedback —
 * submission limits, duplicate protection, the analytics minimum sample,
 * and the deterministic issue-category recommendations.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Submission Limits
    |--------------------------------------------------------------------------
    |
    | Character limits for the public feedback form. The maximum also caps
    | what is sent to the translation provider (cost control).
    |
    */

    'feedback_min_length' => 10,

    'feedback_max_length' => 1000,

    'tourist_name_max_length' => 100,

    /*
    |--------------------------------------------------------------------------
    | Rate Limit and Duplicate Protection
    |--------------------------------------------------------------------------
    |
    | Submissions per IP address per hour (tourists often share one Wi-Fi
    | address, so the limit expires on its own and never blocks an IP for
    | good). A submission with the same normalized text for the same
    | listing inside the duplicate window is not stored as a new row.
    |
    */

    'submissions_per_hour' => 5,

    'duplicate_window_minutes' => 60,

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Suggested Improvements appear for a listing (destination or
    | establishment) only when its negative count is greater than both its
    | positive and neutral counts AND it has at least this many analyzed
    | reviews.
    |
    */

    'minimum_sample' => 5,

    /*
    |--------------------------------------------------------------------------
    | Translation
    |--------------------------------------------------------------------------
    |
    | Feedback that is not clearly English is translated by the provider
    | behind App\Services\TranslationService (credentials and model live in
    | config/services.php 'openai'). english_word_ratio: share of a review's
    | words that must be common English words for it to skip translation.
    | retry_delays_ms: one wait per retry (backoff) for timeouts, rate
    | limits, and server errors. A translation must be between
    | min_length_ratio and max_length_ratio times the original length
    | (plus 50 characters of slack) or it is rejected as implausible.
    |
    */

    'translation' => [
        'english_word_ratio' => 0.6,
        'timeout_seconds' => 20,
        'connect_timeout_seconds' => 5,
        'retry_delays_ms' => [500, 1500],
        'min_length_ratio' => 0.2,
        'max_length_ratio' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Issue Categories and Recommendations
    |--------------------------------------------------------------------------
    |
    | The fixed recurring-issue categories (tbl_feedback_issue_lexicons
    | .fil_issue_category and tbl_feedback_issues.fbi_issue_category values)
    | and their predefined recommendation. 'Other' collects negative feedback
    | with no known issue keyword and has no recommendation. No generative
    | AI is involved: this lookup is the only source of recommendation text.
    |
    */

    'other_issue_category' => 'Other',

    'issue_categories' => [
        'Cleanliness' => 'Improve cleanliness monitoring and waste management.',
        'Maintenance' => 'Improve facility inspection and maintenance schedules.',
        'Crowd Management' => 'Improve visitor monitoring and crowd management measures.',
        'Facilities' => 'Improve facility availability and maintenance.',
        'Pricing' => 'Review pricing transparency and visitor cost concerns.',
        'Customer Service' => 'Improve staff training and visitor assistance.',
        'Accessibility' => 'Improve accessibility facilities and visitor access information.',
        'Safety' => 'Strengthen visitor safety measures and safety information.',
        'Transportation' => 'Improve transportation information and accessibility.',
        'Information' => 'Improve visitor information, signage, and posted schedules.',
        'Environment' => 'Strengthen environmental protection and natural-site conservation measures.',
        'Other' => null,
    ],

];
