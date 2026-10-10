<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — Objective 4 Phase 2: recurring-issue detection for
 * negative feedback (single keywords, phrases, longest match, several
 * issues, negated keywords, the 'Other' fallback, non-negative feedback).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\SentimentClassification;
use App\Services\FeedbackIssueDetector;
use App\Services\SentimentAnalysisService;
use Database\Seeders\FeedbackIssueLexiconSeeder;
use Database\Seeders\SentimentLexiconSeeder;

beforeEach(function () {
    $this->seed(SentimentLexiconSeeder::class);
    $this->seed(FeedbackIssueLexiconSeeder::class);
});

/**
 * Runs the real pipeline order: classify first, then detect issues.
 *
 * @return array<int, string>
 */
function issueCategoriesOf(string $strText): array
{
    $objClass = app(SentimentAnalysisService::class)->analyze($strText)['sentiment_classification'];

    return array_column(app(FeedbackIssueDetector::class)->detect($strText, $objClass), 'category');
}

test('the documented single-keyword issues map to their categories', function (string $strText, string $strCategory) {
    expect(issueCategoriesOf($strText))->toBe([$strCategory]);
})->with([
    'crowded' => ['The beach was crowded', 'Crowd Management'],
    'dirty' => ['The restroom was dirty', 'Cleanliness'],
    'expensive' => ['The entrance fee is expensive', 'Pricing'],
    'broken' => ['The cottage door was broken', 'Maintenance'],
    'rude' => ['The guard was rude', 'Customer Service'],
    'unsafe' => ['The trail felt unsafe', 'Safety'],
]);

test('multi-word phrases are matched as phrases', function () {
    expect(issueCategoriesOf('Bad trip, too many people everywhere'))->toBe(['Crowd Management'])
        ->and(issueCategoriesOf('Bad road and difficult to access'))->toBe(['Transportation', 'Accessibility'])
        ->and(issueCategoriesOf('Bad experience and poorly maintained'))->toBe(['Maintenance']);
});

test('one review can have several issues, reported in order of appearance with their keyword', function () {
    $strText = 'Very crowded and the facilities are poorly maintained';
    $objClass = app(SentimentAnalysisService::class)->analyze($strText)['sentiment_classification'];

    expect($objClass)->toBe(SentimentClassification::Negative)
        ->and(app(FeedbackIssueDetector::class)->detect($strText, $objClass))->toBe([
            ['category' => 'Crowd Management', 'keyword' => 'crowded'],
            ['category' => 'Maintenance', 'keyword' => 'poorly maintained'],
        ]);
});

test('the longest phrase wins, so a phrase is not also counted through its shorter word', function () {
    expect(issueCategoriesOf('Bad: plastic waste on the shore'))->toBe(['Environment'])
        ->and(issueCategoriesOf('Bad: damaged coral near the reef'))->toBe(['Environment']);
});

test('a category is reported once even when several of its keywords match', function () {
    expect(issueCategoriesOf('Dirty, garbage and trash everywhere'))->toBe(['Cleanliness']);
});

test('a negated issue keyword is not detected', function () {
    expect(issueCategoriesOf('The beach was not dirty but the staff were rude and unfriendly'))->toBe(['Customer Service'])
        ->and(issueCategoriesOf('Not crowded at all, but terrible and rude guides'))->toBe(['Customer Service']);
});

test('negative feedback without a known issue is filed under Other with no keyword', function () {
    $strText = 'Terrible and boring trip';
    $objClass = app(SentimentAnalysisService::class)->analyze($strText)['sentiment_classification'];

    expect($objClass)->toBe(SentimentClassification::Negative)
        ->and(app(FeedbackIssueDetector::class)->detect($strText, $objClass))->toBe([['category' => 'Other', 'keyword' => null]])
        // Only negated keywords also falls back to Other.
        ->and(issueCategoriesOf('Terrible and awful visit, though not dirty'))->toBe(['Other']);
});

test('positive, neutral, and unclassified feedback is never checked for issues', function () {
    $objDetector = app(FeedbackIssueDetector::class);

    // "crowded" is an issue keyword, but this review is Neutral.
    expect(issueCategoriesOf('The place is very beautiful but crowded'))->toBe([])
        ->and(issueCategoriesOf('Clean and beautiful, a little expensive'))->toBe([])
        ->and($objDetector->detect('dirty', SentimentClassification::Positive))->toBe([])
        ->and($objDetector->detect('dirty', SentimentClassification::Neutral))->toBe([])
        ->and($objDetector->detect('dirty', null))->toBe([]);
});

test('case and punctuation do not affect issue matching', function () {
    expect(issueCategoriesOf('DIRTY!!! And way TOO-MANY people.'))->toBe(['Cleanliness', 'Crowd Management']);
});
