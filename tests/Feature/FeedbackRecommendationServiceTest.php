<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — Objective 4 Phase 2: the predefined recommendation
 * lookup, the "negative feedback dominates" rule with its minimum sample,
 * and the Common Concerns / Suggested Improvements summary.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Services\FeedbackRecommendationService;

test('every category returns its predefined recommendation; Other and unknown categories return none', function () {
    $objService = app(FeedbackRecommendationService::class);

    expect($objService->recommendationFor('Cleanliness'))->toBe('Improve cleanliness monitoring and waste management.')
        ->and($objService->recommendationFor('Maintenance'))->toBe('Improve facility inspection and maintenance schedules.')
        ->and($objService->recommendationFor('Crowd Management'))->toBe('Improve visitor monitoring and crowd management measures.')
        ->and($objService->recommendationFor('Facilities'))->toBe('Improve facility availability and maintenance.')
        ->and($objService->recommendationFor('Pricing'))->toBe('Review pricing transparency and visitor cost concerns.')
        ->and($objService->recommendationFor('Customer Service'))->toBe('Improve staff training and visitor assistance.')
        ->and($objService->recommendationFor('Accessibility'))->toBe('Improve accessibility facilities and visitor access information.')
        ->and($objService->recommendationFor('Safety'))->toBe('Strengthen visitor safety measures and safety information.')
        ->and($objService->recommendationFor('Transportation'))->toBe('Improve transportation information and accessibility.')
        ->and($objService->recommendationFor('Information'))->toBe('Improve visitor information, signage, and posted schedules.')
        ->and($objService->recommendationFor('Environment'))->toBe('Strengthen environmental protection and natural-site conservation measures.')
        ->and($objService->recommendationFor('Other'))->toBeNull()
        ->and($objService->recommendationFor('Unknown Category'))->toBeNull();
});

test('negative dominates only when it beats both positive and neutral with at least five analyzed reviews', function (int $intPositive, int $intNeutral, int $intNegative, bool $blnExpected) {
    expect(app(FeedbackRecommendationService::class)->isNegativeDominant($intPositive, $intNeutral, $intNegative))->toBe($blnExpected);
})->with([
    'clear negative majority' => [1, 1, 3, true],
    'all negative, exactly five' => [0, 0, 5, true],
    'negative only beats neutral' => [3, 0, 3, false],
    'negative only beats positive' => [0, 3, 3, false],
    'positive majority' => [4, 1, 2, false],
    'below the minimum sample' => [0, 0, 4, false],
    'no reviews' => [0, 0, 0, false],
]);

test('the minimum sample comes from configuration', function () {
    config(['tourist_feedback.minimum_sample' => 3]);

    expect(app(FeedbackRecommendationService::class)->isNegativeDominant(0, 0, 3))->toBeTrue();
});

test('a negative-dominant listing gets ranked concerns and their suggested improvements, without one for Other', function () {
    $arrSummary = app(FeedbackRecommendationService::class)->summarize(1, 1, 4, [
        'Other' => 1,
        'Maintenance' => 2,
        'Crowd Management' => 3,
        'Pricing' => 0,
    ]);

    expect($arrSummary['is_negative_dominant'])->toBeTrue()
        ->and($arrSummary['common_concerns'])->toBe([
            ['category' => 'Crowd Management', 'count' => 3],
            ['category' => 'Maintenance', 'count' => 2],
            ['category' => 'Other', 'count' => 1],
        ])
        ->and($arrSummary['suggested_improvements'])->toBe([
            'Improve visitor monitoring and crowd management measures.',
            'Improve facility inspection and maintenance schedules.',
        ]);
});

test('concerns are still listed when negative feedback does not dominate, but no improvements are suggested', function () {
    $arrSummary = app(FeedbackRecommendationService::class)->summarize(6, 2, 2, ['Pricing' => 2]);

    expect($arrSummary['is_negative_dominant'])->toBeFalse()
        ->and($arrSummary['common_concerns'])->toBe([['category' => 'Pricing', 'count' => 2]])
        ->and($arrSummary['suggested_improvements'])->toBe([]);
});

test('tied concerns keep the configured category order', function () {
    $arrSummary = app(FeedbackRecommendationService::class)->summarize(0, 0, 6, ['Safety' => 2, 'Cleanliness' => 2, 'Other' => 2]);

    expect(array_column($arrSummary['common_concerns'], 'category'))->toBe(['Cleanliness', 'Safety', 'Other']);
});

test('a listing with no issues has no concerns and no improvements', function () {
    expect(app(FeedbackRecommendationService::class)->summarize(0, 0, 5, []))->toBe([
        'is_negative_dominant' => true,
        'common_concerns' => [],
        'suggested_improvements' => [],
    ]);
});
