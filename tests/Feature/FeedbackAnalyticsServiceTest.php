<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — Objective 4 Phase 5: the analytics calculations
 * (processing-status counts, sentiment distribution and average, monthly
 * trend, reporting periods in Asia/Manila, common concerns, per-listing
 * summaries with the recommendation rule, dashboard cards). Only analyzed
 * feedback may ever reach a sentiment number.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\FeedbackAnalysisStatus;
use App\Enums\SentimentClassification;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Services\FeedbackAnalyticsService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    Http::preventStrayRequests();
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
});

function analyticsListing(string $strKind = 'destination', string $strMunicipality = 'City of Mati', array $arrOverrides = []): Listing
{
    $objMunicipality = Municipality::query()->firstOrCreate(['mun_code' => Str::upper(Str::slug($strMunicipality, '_'))], ['mun_name' => $strMunicipality]);
    $blnIsDestination = $strKind === 'destination';
    $objCategory = Category::query()->where('cat_name', $blnIsDestination ? 'Tourist Destinations' : 'Accommodation')->firstOrFail();

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug(($blnIsDestination ? 'analytics-falls-' : 'analytics-inn-').Str::random(6)),
        'lst_name' => $blnIsDestination ? 'Analytics Falls' : 'Analytics Inn',
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => $blnIsDestination ? 'Active' : 'PUBLISHED',
    ], $arrOverrides));
}

/**
 * A feedback row with any system fields (status, sentiment, score, date).
 *
 * @param  array<string, mixed>  $arrFields
 * @param  array<int, string>  $arrIssues
 */
function analyticsFeedback(Listing $objListing, array $arrFields = [], array $arrIssues = []): Feedback
{
    $objFeedback = $objListing->feedbacks()->make(['fbk_original_text' => $arrFields['text'] ?? 'Feedback '.Str::random(8)]);
    unset($arrFields['text']);
    $objFeedback->forceFill(array_merge([
        'fbk_consent_at' => now(),
        'fbk_content_hash' => hash('sha256', Str::random(16)),
    ], $arrFields))->save();

    foreach ($arrIssues as $strCategory) {
        $objFeedback->issues()->create(['fbi_issue_category' => $strCategory, 'fbi_matched_keyword' => null]);
    }

    return $objFeedback;
}

/**
 * An analyzed row with a class and score.
 *
 * @param  array<int, string>  $arrIssues
 * @param  array<string, mixed>  $arrExtra
 */
function analyticsAnalyzed(Listing $objListing, SentimentClassification $objClass, float $fltScore, array $arrIssues = [], array $arrExtra = []): Feedback
{
    return analyticsFeedback($objListing, array_merge([
        'fbk_status' => FeedbackAnalysisStatus::Analyzed,
        'fbk_sentiment' => $objClass,
        'fbk_sentiment_score' => $fltScore,
        'fbk_translated_text' => 'Translated',
        'fbk_analyzed_at' => now(),
    ], $arrExtra), $arrIssues);
}

function analyticsService(): FeedbackAnalyticsService
{
    return app(FeedbackAnalyticsService::class);
}

function analyticsPeriod(array $arrQuery = []): array
{
    return analyticsService()->resolvePeriod(Request::create('/', 'GET', $arrQuery));
}

test('status counts include every processing status', function () {
    $objListing = analyticsListing();
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.5);
    analyticsFeedback($objListing);
    analyticsFeedback($objListing, ['fbk_status' => FeedbackAnalysisStatus::Failed]);
    analyticsFeedback($objListing, ['fbk_status' => FeedbackAnalysisStatus::Failed]);
    analyticsFeedback($objListing, ['fbk_status' => FeedbackAnalysisStatus::Rejected]);

    expect(analyticsService()->statusCounts(Feedback::query()))->toBe([
        'pending' => 1, 'analyzed' => 1, 'failed' => 2, 'rejected' => 1, 'total' => 5,
    ]);
});

test('the sentiment summary counts analyzed feedback only, with shares and the average score', function () {
    $objListing = analyticsListing();
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.4);
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.2);
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.3);
    analyticsAnalyzed($objListing, SentimentClassification::Neutral, 0.0);
    analyticsAnalyzed($objListing, SentimentClassification::Negative, -0.5);
    analyticsAnalyzed($objListing, SentimentClassification::Negative, -0.25);
    // Never counted, even with leftover analysis values.
    analyticsFeedback($objListing, ['fbk_status' => FeedbackAnalysisStatus::Failed, 'fbk_sentiment' => SentimentClassification::Positive, 'fbk_sentiment_score' => 1]);
    analyticsFeedback($objListing);
    analyticsFeedback($objListing, ['fbk_status' => FeedbackAnalysisStatus::Rejected]);

    expect(analyticsService()->sentimentSummary(Feedback::query()))->toBe([
        'positive' => 3, 'neutral' => 1, 'negative' => 2, 'analyzed' => 6,
        'positive_pct' => 50, 'neutral_pct' => 17, 'negative_pct' => 33,
        'average_score' => 0.025,
    ]);
});

test('an empty scope gives zero counts and no average instead of fabricated values', function () {
    analyticsFeedback(analyticsListing());

    expect(analyticsService()->sentimentSummary(Feedback::query()))->toBe([
        'positive' => 0, 'neutral' => 0, 'negative' => 0, 'analyzed' => 0,
        'positive_pct' => 0, 'neutral_pct' => 0, 'negative_pct' => 0,
        'average_score' => null,
    ])
        ->and(analyticsService()->commonConcerns(Feedback::query()))->toBe([])
        ->and(analyticsService()->listingSummaries(Feedback::query()->whereRaw('1 = 0')))->toHaveCount(0);
});

test('the monthly trend covers the last 12 months with empty months marked as zero totals', function () {
    $objListing = analyticsListing();
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.5, [], ['fbk_created_at' => '2026-10-03 09:00:00']);
    analyticsAnalyzed($objListing, SentimentClassification::Negative, -0.5, [], ['fbk_created_at' => '2026-10-04 09:00:00']);
    analyticsAnalyzed($objListing, SentimentClassification::Neutral, 0.0, [], ['fbk_created_at' => '2026-07-20 09:00:00']);
    analyticsFeedback($objListing, ['fbk_created_at' => '2026-08-01 09:00:00']);

    $arrTrend = analyticsService()->monthlyTrend(Feedback::query(), analyticsPeriod());
    $arrByMonth = collect($arrTrend)->keyBy('month');

    expect($arrTrend)->toHaveCount(12)
        ->and($arrTrend[0]['month'])->toBe('2025-11')
        ->and($arrTrend[11]['month'])->toBe('2026-10')
        ->and($arrByMonth['2026-10'])->toMatchArray(['total' => 2, 'positive' => 1, 'negative' => 1, 'positive_pct' => 50, 'label' => 'Oct 2026'])
        ->and($arrByMonth['2026-07'])->toMatchArray(['total' => 1, 'neutral' => 1, 'positive_pct' => 0])
        // A month with only pending feedback is "No feedback", not 0% positive.
        ->and($arrByMonth['2026-08']['total'])->toBe(0);

    $arrThisMonth = analyticsService()->monthlyTrend(Feedback::query(), analyticsPeriod(['period' => 'this_month']));

    expect($arrThisMonth)->toHaveCount(1)->and($arrThisMonth[0]['month'])->toBe('2026-10');
});

test('reporting periods use Philippine time boundaries on the submission date', function () {
    $objListing = analyticsListing();
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.5, [], ['fbk_created_at' => '2026-09-30 23:59:59']);
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.5, [], ['fbk_created_at' => '2026-10-01 00:00:00']);
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.5, [], ['fbk_created_at' => '2025-12-31 23:59:59']);
    $fnCount = fn (array $arrQuery) => analyticsService()->filtered(Feedback::query(), analyticsPeriod($arrQuery))->count();

    expect($fnCount([]))->toBe(3)
        ->and($fnCount(['period' => 'this_month']))->toBe(1)
        ->and($fnCount(['period' => 'last_month']))->toBe(1)
        ->and($fnCount(['period' => 'this_year']))->toBe(2)
        ->and($fnCount(['period' => 'custom', 'from' => '2025-12-31', 'to' => '2026-09-30']))->toBe(2)
        ->and($fnCount(['period' => 'unknown']))->toBe(3);
});

test('a custom range is validated and never guessed', function () {
    $arrNoDates = analyticsPeriod(['period' => 'custom']);
    $arrReversed = analyticsPeriod(['period' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01']);
    $arrInvalid = analyticsPeriod(['period' => 'custom', 'from' => '2026-02-30', 'to' => '2026-03-01']);
    $arrValid = analyticsPeriod(['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-10']);

    expect($arrNoDates)->toMatchArray(['key' => 'custom', 'from' => null, 'error' => null])
        ->and($arrReversed['from'])->toBeNull()
        ->and($arrReversed['error'])->not->toBeNull()
        ->and($arrInvalid['error'])->not->toBeNull()
        ->and($arrValid['from']->toDateTimeString())->toBe('2026-10-01 00:00:00')
        ->and($arrValid['to']->toDateTimeString())->toBe('2026-10-10 23:59:59')
        ->and($arrValid['label'])->toBe('Oct 1, 2026 – Oct 10, 2026');
});

test('destination and establishment feedback can be filtered apart', function () {
    analyticsAnalyzed(analyticsListing('destination'), SentimentClassification::Positive, 0.5);
    analyticsAnalyzed(analyticsListing('establishment'), SentimentClassification::Negative, -0.5);
    analyticsAnalyzed(analyticsListing('establishment'), SentimentClassification::Negative, -0.5);
    $arrPeriod = analyticsPeriod();

    expect(analyticsService()->filtered(Feedback::query(), $arrPeriod, 'destinations')->count())->toBe(1)
        ->and(analyticsService()->filtered(Feedback::query(), $arrPeriod, 'establishments')->count())->toBe(2)
        ->and(analyticsService()->filtered(Feedback::query(), $arrPeriod, 'all')->count())->toBe(3);
});

test('common concerns rank issue categories of analyzed feedback only', function () {
    $objListing = analyticsListing();
    analyticsAnalyzed($objListing, SentimentClassification::Negative, -0.5, ['Pricing', 'Cleanliness']);
    analyticsAnalyzed($objListing, SentimentClassification::Negative, -0.5, ['Cleanliness']);
    analyticsAnalyzed($objListing, SentimentClassification::Negative, -0.5, ['Safety']);
    // Issue rows left on a failed row are never counted.
    analyticsFeedback($objListing, ['fbk_status' => FeedbackAnalysisStatus::Failed], ['Safety', 'Safety Extra']);

    expect(analyticsService()->commonConcerns(Feedback::query()))->toBe([
        ['category' => 'Cleanliness', 'count' => 2],
        ['category' => 'Pricing', 'count' => 1],
        ['category' => 'Safety', 'count' => 1],
    ]);
});

test('listing summaries apply the recommendation rule: negative-dominant with at least five analyzed reviews', function () {
    $objDominant = analyticsListing('destination', 'City of Mati', ['lst_name' => 'Dominant Falls']);
    analyticsAnalyzed($objDominant, SentimentClassification::Positive, 0.3);
    foreach (range(1, 3) as $intIndex) {
        analyticsAnalyzed($objDominant, SentimentClassification::Negative, -0.4, ['Cleanliness']);
    }
    analyticsAnalyzed($objDominant, SentimentClassification::Negative, -0.4, ['Pricing']);
    analyticsFeedback($objDominant, ['fbk_status' => FeedbackAnalysisStatus::Failed]);

    $objSmallSample = analyticsListing('establishment', 'City of Mati', ['lst_name' => 'Small Sample Inn']);
    analyticsAnalyzed($objSmallSample, SentimentClassification::Positive, 0.3);
    foreach (range(1, 3) as $intIndex) {
        analyticsAnalyzed($objSmallSample, SentimentClassification::Negative, -0.4, ['Customer Service']);
    }

    $objMostlyPositive = analyticsListing('establishment', 'City of Mati', ['lst_name' => 'Happy Inn']);
    foreach (range(1, 3) as $intIndex) {
        analyticsAnalyzed($objMostlyPositive, SentimentClassification::Positive, 0.5);
    }
    analyticsAnalyzed($objMostlyPositive, SentimentClassification::Negative, -0.3, ['Pricing']);
    analyticsAnalyzed($objMostlyPositive, SentimentClassification::Negative, -0.3, ['Pricing']);

    $colRows = analyticsService()->listingSummaries(Feedback::query())->keyBy('name');

    expect($colRows['Dominant Falls'])->toMatchArray([
        'type' => 'Destination',
        'category' => 'Tourist Destinations',
        'feedback_count' => 6,
        'top_concern' => 'Cleanliness',
        'has_minimum_sample' => true,
        'is_negative_dominant' => true,
        'improvements' => [
            'Improve cleanliness monitoring and waste management.',
            'Review pricing transparency and visitor cost concerns.',
        ],
    ])
        ->and($colRows['Dominant Falls']['sentiment'])->toMatchArray(['positive' => 1, 'negative' => 4, 'analyzed' => 5])
        ->and($colRows['Small Sample Inn'])->toMatchArray([
            'type' => 'Establishment',
            'top_concern' => 'Customer Service',
            'has_minimum_sample' => false,
            'improvements' => [],
        ])
        ->and($colRows['Happy Inn'])->toMatchArray([
            'top_concern' => 'Pricing',
            'is_negative_dominant' => false,
            'improvements' => [],
        ]);
});

test('a listing report shows the current listing status and stays available after unpublishing', function () {
    $objListing = analyticsListing('establishment');
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.5);
    $objListing->forceFill(['lst_status' => 'UNPUBLISHED'])->save();

    $arrReport = analyticsService()->listingReport(analyticsService()->filtered(Feedback::query(), analyticsPeriod(), 'all', $objListing), analyticsPeriod());

    expect(analyticsService()->listingStatusLabel($objListing))->toBe('Unpublished')
        ->and($arrReport['sentiment']['analyzed'])->toBe(1)
        ->and($arrReport['status']['total'])->toBe(1);
});

test('dashboard cards replace only the sample feedback cards with real numbers', function () {
    $objListing = analyticsListing();
    analyticsAnalyzed($objListing, SentimentClassification::Positive, 0.5);
    analyticsAnalyzed($objListing, SentimentClassification::Negative, -0.5);
    analyticsFeedback($objListing);

    $arrCards = analyticsService()->withFeedbackCards([
        ['label' => 'Tourist Arrivals', 'value' => '99', 'delta' => 'x', 'tone' => 'neutral'],
        ['label' => 'Tourist Feedback', 'value' => '1,344', 'delta' => 'sample', 'tone' => 'success'],
        ['label' => 'Overall Sentiment', 'value' => '88% Positive', 'delta' => 'sample', 'tone' => 'success'],
    ], Feedback::query());

    expect($arrCards[0]['value'])->toBe('99')
        ->and($arrCards[1])->toMatchArray(['value' => '2', 'delta' => '1 awaiting analysis'])
        ->and($arrCards[2])->toMatchArray(['value' => '50% Positive', 'delta' => '2 analyzed']);
});

test('the analytics scope follows the user: PTO all, LGU own municipality, establishment own listing', function () {
    $objMatiListing = analyticsListing('establishment', 'City of Mati');
    analyticsAnalyzed($objMatiListing, SentimentClassification::Positive, 0.5);
    analyticsAnalyzed(analyticsListing('destination', 'City of Mati'), SentimentClassification::Positive, 0.5);
    analyticsAnalyzed(analyticsListing('destination', 'Baganga'), SentimentClassification::Positive, 0.5);
    $objMati = Municipality::query()->where('mun_name', 'City of Mati')->firstOrFail();

    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $objLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $objMati->mun_id]);
    $objOwner = User::factory()->create(['usr_role' => UserRole::Establishment, 'mun_id' => $objMati->mun_id, 'lst_id' => $objMatiListing->lst_id]);

    expect(analyticsService()->scopedQuery($objPto)->count())->toBe(3)
        ->and(analyticsService()->scopedQuery($objLgu)->count())->toBe(2)
        ->and(analyticsService()->scopedQuery($objOwner)->count())->toBe(1);
});
