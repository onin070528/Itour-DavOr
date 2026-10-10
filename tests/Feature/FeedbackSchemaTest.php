<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — Objective 4 Phase 1: the tourist feedback tables, models
 * (relationships, casts, protected system fields), feedback eligibility for
 * destinations and establishments, feedback authorization per role, the
 * destination/establishment type filters, the lexicon seeders, and the
 * recommendations configuration.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\FeedbackAnalysisStatus;
use App\Enums\ReportingMethod;
use App\Enums\SentimentClassification;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\FeedbackIssueLexicon;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SecurityLog;
use App\Models\SentimentLexicon;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FeedbackIssueLexiconSeeder;
use Database\Seeders\SentimentLexiconSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function feedbackMunicipality(string $strName, string $strCode): Municipality
{
    return Municipality::query()->firstOrCreate(['mun_code' => $strCode], ['mun_name' => $strName]);
}

function feedbackDestination(Municipality $objMunicipality, string $strStatus = 'Active'): Listing
{
    test()->seed(CategorySeeder::class);
    $objCategory = Category::query()->where('cat_name', 'Tourist Destinations')->firstOrFail();

    return Listing::query()->create([
        'lst_slug' => Str::slug('feedback-falls-'.Str::random(6)),
        'lst_name' => 'Feedback Falls',
        'lst_category' => 'destinations',
        'cat_id' => $objCategory->cat_id,
        'lst_type' => 'Waterfall',
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => $strStatus,
    ]);
}

/**
 * A tourism establishment listing. Reporting method and QR switch are not
 * fillable, so they are set explicitly when a test needs them.
 *
 * @param  array<string, mixed>  $arrOverrides
 */
function feedbackEstablishment(Municipality $objMunicipality, string $strStatus = 'PUBLISHED', array $arrOverrides = []): Listing
{
    test()->seed(CategorySeeder::class);
    $objCategory = Category::query()->where('cat_name', $arrOverrides['category'] ?? 'Accommodation')->firstOrFail();
    unset($arrOverrides['category']);

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug('feedback-inn-'.Str::random(6)),
        'lst_name' => 'Feedback Inn',
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_type' => 'Resort',
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => $strStatus,
    ], $arrOverrides));
}

/**
 * @param  array<string, mixed>  $arrAttributes
 */
function feedbackUser(UserRole $objRole, array $arrAttributes = []): User
{
    return User::factory()->create(array_merge(['usr_role' => $objRole], $arrAttributes));
}

/**
 * Stores feedback the way the public pipeline will: tourist input through
 * the fillable fields, system fields through forceFill().
 *
 * @param  array<string, mixed>  $arrSystemFields
 */
function feedbackFor(Listing $objListing, string $strText = 'The beach is clean and beautiful', array $arrSystemFields = []): Feedback
{
    $objFeedback = $objListing->feedbacks()->make(['fbk_original_text' => $strText]);
    $objFeedback->forceFill(array_merge([
        'fbk_consent_at' => now(),
        'fbk_content_hash' => hash('sha256', $objListing->lst_id.'|'.Str::lower($strText)),
    ], $arrSystemFields))->save();

    return $objFeedback;
}

test('the four Objective 4 tables exist with their prefixed columns', function () {
    expect(Schema::hasColumns('tbl_feedbacks', [
        'fbk_id', 'lst_id', 'fbk_tourist_name', 'fbk_original_text', 'fbk_translated_text',
        'fbk_detected_language', 'fbk_visit_date', 'fbk_consent_at', 'fbk_content_hash', 'fbk_status',
        'fbk_positive_count', 'fbk_negative_count', 'fbk_total_word_count', 'fbk_sentiment_score',
        'fbk_sentiment', 'fbk_matched_terms', 'fbk_failure_reason', 'fbk_analyzed_at',
        'fbk_created_at', 'fbk_updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('tbl_feedback_issues', ['fbi_id', 'fbk_id', 'fbi_issue_category', 'fbi_matched_keyword', 'fbi_created_at']))->toBeTrue()
        ->and(Schema::hasColumns('tbl_sentiment_lexicons', ['slx_id', 'slx_word', 'slx_polarity', 'slx_weight', 'slx_created_at', 'slx_updated_at']))->toBeTrue()
        ->and(Schema::hasColumns('tbl_feedback_issue_lexicons', ['fil_id', 'fil_keyword', 'fil_issue_category', 'fil_weight', 'fil_created_at', 'fil_updated_at']))->toBeTrue()
        // No IP address or tourist location is ever stored with feedback.
        ->and(Schema::hasColumn('tbl_feedbacks', 'fbk_ip_address'))->toBeFalse();
});

test('consent is required at the database level', function () {
    $objListing = feedbackDestination(feedbackMunicipality('City of Mati', 'MATI'));

    expect(fn () => DB::table('tbl_feedbacks')->insert([
        'lst_id' => $objListing->lst_id,
        'fbk_original_text' => 'No consent given here',
        'fbk_content_hash' => str_repeat('a', 64),
    ]))->toThrow(QueryException::class);
});

test('PostgreSQL rejects an unknown status, sentiment, or out-of-range score', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('CHECK constraints exist on PostgreSQL only: DB_CONNECTION=pgsql DB_DATABASE=itour_testing php artisan test --filter=FeedbackSchemaTest');
    }

    $objFeedback = feedbackFor(feedbackDestination(feedbackMunicipality('City of Mati', 'MATI')));

    foreach (['fbk_status' => 'approved', 'fbk_sentiment' => 'mixed', 'fbk_sentiment_score' => 1.5] as $strColumn => $mixValue) {
        // Each attempt runs in its own savepoint so one rejection doesn't
        // abort the test transaction and fake the next rejection.
        expect(fn () => DB::transaction(fn () => DB::table('tbl_feedbacks')->where('fbk_id', $objFeedback->fbk_id)->update([$strColumn => $mixValue])))
            ->toThrow(QueryException::class);
        expect(DB::table('tbl_feedbacks')->where('fbk_id', $objFeedback->fbk_id)->value('fbk_status'))->toBe('pending');
    }
});

test('new feedback starts pending and system fields cannot be mass assigned', function () {
    $objListing = feedbackDestination(feedbackMunicipality('City of Mati', 'MATI'));

    $objFeedback = $objListing->feedbacks()->make([
        'fbk_original_text' => 'Nindot kaayo ang place pero crowded.',
        'fbk_tourist_name' => 'Ana',
        'fbk_visit_date' => '2026-10-01',
        // None of these may come from request input.
        'fbk_status' => 'analyzed',
        'fbk_sentiment_score' => 0.9,
        'fbk_sentiment' => 'positive',
        'fbk_translated_text' => 'Injected translation',
        'fbk_consent_at' => '2020-01-01 00:00:00',
        'lst_id' => 999999,
    ]);

    expect($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Pending)
        ->and($objFeedback->fbk_sentiment_score)->toBeNull()
        ->and($objFeedback->fbk_sentiment)->toBeNull()
        ->and($objFeedback->fbk_translated_text)->toBeNull()
        ->and($objFeedback->fbk_consent_at)->toBeNull()
        ->and($objFeedback->lst_id)->toBe($objListing->lst_id)
        ->and($objFeedback->fbk_tourist_name)->toBe('Ana')
        ->and($objFeedback->fbk_visit_date->toDateString())->toBe('2026-10-01');
});

test('analysis results are cast to enums, a 4-decimal score, and an array of matched terms', function () {
    $objListing = feedbackDestination(feedbackMunicipality('City of Mati', 'MATI'));
    $objFeedback = feedbackFor($objListing, 'The beach is clean and beautiful', [
        'fbk_status' => FeedbackAnalysisStatus::Analyzed,
        'fbk_translated_text' => 'The beach is clean and beautiful',
        'fbk_positive_count' => 2,
        'fbk_negative_count' => 0,
        'fbk_total_word_count' => 6,
        'fbk_sentiment_score' => 2 / 6,
        'fbk_sentiment' => SentimentClassification::Positive,
        'fbk_matched_terms' => ['positive' => ['clean', 'beautiful'], 'negative' => []],
        'fbk_analyzed_at' => now(),
    ])->fresh();

    expect($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Analyzed)
        ->and($objFeedback->fbk_sentiment)->toBe(SentimentClassification::Positive)
        ->and($objFeedback->fbk_sentiment_score)->toBe('0.3333')
        ->and($objFeedback->fbk_matched_terms['positive'])->toBe(['clean', 'beautiful'])
        ->and($objFeedback->isAnalyzed())->toBeTrue()
        ->and($objFeedback->listing->is($objListing))->toBeTrue()
        ->and($objListing->feedbacks()->count())->toBe(1);
});

test('a feedback row has at most one issue row per category', function () {
    $objFeedback = feedbackFor(feedbackDestination(feedbackMunicipality('City of Mati', 'MATI')), 'Very crowded and the facilities are poorly maintained');

    $objFeedback->issues()->create(['fbi_issue_category' => 'Crowd Management', 'fbi_matched_keyword' => 'crowded']);
    $objFeedback->issues()->create(['fbi_issue_category' => 'Maintenance', 'fbi_matched_keyword' => 'poorly maintained']);

    expect($objFeedback->issues()->count())->toBe(2)
        ->and(fn () => $objFeedback->issues()->create(['fbi_issue_category' => 'Maintenance', 'fbi_matched_keyword' => 'broken']))
        ->toThrow(QueryException::class);
});

test('feedback history survives archiving or suspending its listing, and the listing cannot be deleted under it', function () {
    $objListing = feedbackDestination(feedbackMunicipality('City of Mati', 'MATI'));
    $objFeedback = feedbackFor($objListing);

    $objListing->forceFill(['lst_status' => 'Archived'])->save();
    expect($objFeedback->fresh()->listing->lst_status)->toBe('Archived');

    $objListing->forceFill(['lst_status' => 'Suspended'])->save();
    expect($objFeedback->fresh()->lst_id)->toBe($objListing->lst_id);

    // Savepoint: on PostgreSQL the rejected delete must not abort the test transaction.
    expect(fn () => DB::transaction(fn () => $objListing->delete()))->toThrow(QueryException::class);
    expect(Feedback::query()->count())->toBe(1);
});

test('only analyzed feedback is counted by the analyzed scope', function () {
    $objListing = feedbackDestination(feedbackMunicipality('City of Mati', 'MATI'));

    feedbackFor($objListing, 'Pending entry text');
    feedbackFor($objListing, 'Failed entry text', ['fbk_status' => FeedbackAnalysisStatus::Failed, 'fbk_failure_reason' => 'Translation unavailable']);
    feedbackFor($objListing, 'Rejected entry text', ['fbk_status' => FeedbackAnalysisStatus::Rejected]);
    feedbackFor($objListing, 'Analyzed entry text', ['fbk_status' => FeedbackAnalysisStatus::Analyzed]);

    expect(Feedback::query()->analyzed()->count())->toBe(1)
        ->and(FeedbackAnalysisStatus::Analyzed->isCountedInAnalytics())->toBeTrue()
        ->and(FeedbackAnalysisStatus::Failed->isCountedInAnalytics())->toBeFalse()
        ->and(FeedbackAnalysisStatus::Pending->isCountedInAnalytics())->toBeFalse()
        ->and(FeedbackAnalysisStatus::Rejected->isCountedInAnalytics())->toBeFalse();
});

test('published destinations and published establishments accept feedback; every non-public status does not', function () {
    $objMati = feedbackMunicipality('City of Mati', 'MATI');

    expect(feedbackDestination($objMati, 'Active')->isAcceptingFeedback())->toBeTrue()
        ->and(feedbackEstablishment($objMati, 'PUBLISHED')->isAcceptingFeedback())->toBeTrue();

    foreach (['DRAFT', 'FOR_PTO_REVIEW', 'FOR_CORRECTION', 'Suspended', 'Archived'] as $strStatus) {
        expect(feedbackDestination($objMati, $strStatus)->isAcceptingFeedback())->toBeFalse();
    }

    foreach (['DRAFT', 'FOR_LGU_REVIEW', 'FOR_PTO_REVIEW', 'FOR_CORRECTION', 'UNPUBLISHED', 'Suspended', 'Archived'] as $strStatus) {
        expect(feedbackEstablishment($objMati, $strStatus)->isAcceptingFeedback())->toBeFalse();
    }
});

test('tour guides never accept feedback, but Manual/Paper and QR-off establishments do', function () {
    $objMati = feedbackMunicipality('City of Mati', 'MATI');

    $objTourGuide = feedbackEstablishment($objMati, 'PUBLISHED', ['category' => 'Travel & Tours', 'lst_type' => config('establishment_categories.tour_guide_type')]);
    $objLegacyTourGuide = feedbackEstablishment($objMati, 'PUBLISHED', ['category' => 'Travel & Tours', 'lst_type' => Listing::LEGACY_TOUR_GUIDE_TYPE]);

    $objManualPaper = feedbackEstablishment($objMati);
    $objManualPaper->forceFill(['lst_reporting_mode' => ReportingMethod::ManualPaper->value, 'lst_is_qr_enabled' => false])->save();

    $objOnlineQrOff = feedbackEstablishment($objMati);
    $objOnlineQrOff->forceFill(['lst_reporting_mode' => ReportingMethod::OnlineItour->value, 'lst_is_qr_enabled' => false])->save();

    expect($objTourGuide->isAcceptingFeedback())->toBeFalse()
        ->and($objLegacyTourGuide->isAcceptingFeedback())->toBeFalse()
        ->and($objManualPaper->fresh()->isAcceptingFeedback())->toBeTrue()
        ->and($objOnlineQrOff->fresh()->isAcceptingFeedback())->toBeTrue();
});

test('the accepting-feedback query scope matches isAcceptingFeedback() row for row', function () {
    $objMati = feedbackMunicipality('City of Mati', 'MATI');

    feedbackDestination($objMati, 'Active');
    feedbackDestination($objMati, 'Archived');
    feedbackEstablishment($objMati, 'PUBLISHED');
    feedbackEstablishment($objMati, 'PUBLISHED', ['lst_type' => null]);
    feedbackEstablishment($objMati, 'UNPUBLISHED');
    feedbackEstablishment($objMati, 'Suspended');
    feedbackEstablishment($objMati, 'PUBLISHED', ['category' => 'Travel & Tours', 'lst_type' => config('establishment_categories.tour_guide_type')]);

    $arrScopeIds = Listing::query()->acceptingFeedback()->orderBy('lst_id')->pluck('lst_id')->all();
    $arrMethodIds = Listing::query()->orderBy('lst_id')->get()->filter->isAcceptingFeedback()->pluck('lst_id')->values()->all();

    expect($arrScopeIds)->toBe($arrMethodIds)
        ->and($arrScopeIds)->toHaveCount(3);
});

test('feedback visibility: PTO sees all, LGU its municipality, an establishment only its own listing', function () {
    $objMati = feedbackMunicipality('City of Mati', 'MATI');
    $objBaganga = feedbackMunicipality('Baganga', 'BAGANGA');

    $objMatiDestination = feedbackDestination($objMati);
    $objMatiEstablishment = feedbackEstablishment($objMati);
    $objOtherMatiEstablishment = feedbackEstablishment($objMati);
    $objBagangaEstablishment = feedbackEstablishment($objBaganga);

    feedbackFor($objMatiDestination, 'Mati destination feedback');
    feedbackFor($objMatiEstablishment, 'Mati establishment feedback');
    feedbackFor($objOtherMatiEstablishment, 'Other Mati establishment feedback');
    feedbackFor($objBagangaEstablishment, 'Baganga establishment feedback');

    $objPto = feedbackUser(UserRole::PtoAdministrator);
    $objMatiLgu = feedbackUser(UserRole::Lgu, ['usr_organization_subtitle' => $objMati->mun_name, 'mun_id' => $objMati->mun_id]);
    $objOwner = feedbackUser(UserRole::Establishment, ['mun_id' => $objMati->mun_id, 'lst_id' => $objMatiEstablishment->lst_id]);

    $fnTexts = fn (User $objUser) => Feedback::query()->visibleTo($objUser)->orderBy('fbk_original_text')->pluck('fbk_original_text')->all();

    expect(Feedback::query()->visibleTo($objPto)->count())->toBe(4)
        ->and($fnTexts($objMatiLgu))->toBe(['Mati destination feedback', 'Mati establishment feedback', 'Other Mati establishment feedback'])
        // Never the destination in its municipality, never another establishment.
        ->and($fnTexts($objOwner))->toBe(['Mati establishment feedback']);
});

test('an unlinked establishment account and an LGU account without a municipality see no feedback', function () {
    $objMati = feedbackMunicipality('City of Mati', 'MATI');
    feedbackFor(feedbackDestination($objMati), 'Mati destination feedback');
    feedbackFor(feedbackEstablishment($objMati), 'Mati establishment feedback');

    $objUnlinkedOwner = feedbackUser(UserRole::Establishment, ['mun_id' => $objMati->mun_id, 'lst_id' => null]);
    $objUnscopedLgu = feedbackUser(UserRole::Lgu, ['mun_id' => null]);

    expect(Feedback::query()->visibleTo($objUnlinkedOwner)->count())->toBe(0)
        ->and(Feedback::query()->visibleTo($objUnscopedLgu)->count())->toBe(0);
});

test('viewFeedback() gives the same answers as the feedback scope and logs a cross-municipality LGU attempt', function () {
    $objMati = feedbackMunicipality('City of Mati', 'MATI');
    $objBaganga = feedbackMunicipality('Baganga', 'BAGANGA');

    $objMatiDestination = feedbackDestination($objMati);
    $objMatiEstablishment = feedbackEstablishment($objMati);
    $objOtherMatiEstablishment = feedbackEstablishment($objMati);
    $objBagangaDestination = feedbackDestination($objBaganga);

    $objPto = feedbackUser(UserRole::PtoAdministrator);
    $objMatiLgu = feedbackUser(UserRole::Lgu, ['usr_organization_subtitle' => $objMati->mun_name, 'mun_id' => $objMati->mun_id]);
    $objUnscopedLgu = feedbackUser(UserRole::Lgu, ['mun_id' => null]);
    $objOwner = feedbackUser(UserRole::Establishment, ['mun_id' => $objMati->mun_id, 'lst_id' => $objMatiEstablishment->lst_id]);
    $objUnlinkedOwner = feedbackUser(UserRole::Establishment, ['mun_id' => $objMati->mun_id, 'lst_id' => null]);

    expect($objPto->can('viewFeedback', $objBagangaDestination))->toBeTrue()
        ->and($objMatiLgu->can('viewFeedback', $objMatiDestination))->toBeTrue()
        ->and($objMatiLgu->can('viewFeedback', $objMatiEstablishment))->toBeTrue()
        ->and($objUnscopedLgu->can('viewFeedback', $objMatiDestination))->toBeFalse()
        ->and($objOwner->can('viewFeedback', $objMatiEstablishment))->toBeTrue()
        ->and($objOwner->can('viewFeedback', $objMatiDestination))->toBeFalse()
        ->and($objOwner->can('viewFeedback', $objOtherMatiEstablishment))->toBeFalse()
        ->and($objUnlinkedOwner->can('viewFeedback', $objMatiEstablishment))->toBeFalse();

    // The existing directory permission is unchanged: an establishment may
    // still view a destination in its municipality, just not its feedback.
    expect($objOwner->can('view', $objMatiDestination))->toBeTrue();

    $intLogsBefore = SecurityLog::query()->count();

    expect($objMatiLgu->can('viewFeedback', $objBagangaDestination))->toBeFalse()
        ->and(SecurityLog::query()->count())->toBe($intLogsBefore + 1);
});

test('destination and establishment feedback are told apart by the listing current category', function () {
    $objMati = feedbackMunicipality('City of Mati', 'MATI');
    feedbackFor(feedbackDestination($objMati), 'Destination one');
    feedbackFor(feedbackDestination($objMati), 'Destination two');
    feedbackFor(feedbackEstablishment($objMati), 'Establishment one');

    expect(Feedback::query()->forDestinations()->count())->toBe(2)
        ->and(Feedback::query()->forEstablishments()->pluck('fbk_original_text')->all())->toBe(['Establishment one'])
        ->and(Feedback::query()->forDestinations()->forEstablishments()->count())->toBe(0);
});

test('an establishment keeps its feedback history after it stops accepting new feedback', function () {
    $objMati = feedbackMunicipality('City of Mati', 'MATI');
    $objEstablishment = feedbackEstablishment($objMati);
    feedbackFor($objEstablishment, 'Earlier establishment feedback', ['fbk_status' => FeedbackAnalysisStatus::Analyzed]);
    $objOwner = feedbackUser(UserRole::Establishment, ['mun_id' => $objMati->mun_id, 'lst_id' => $objEstablishment->lst_id]);

    foreach (['UNPUBLISHED', 'Suspended', 'Archived'] as $strStatus) {
        $objEstablishment->forceFill(['lst_status' => $strStatus])->save();

        expect($objEstablishment->fresh()->isAcceptingFeedback())->toBeFalse()
            ->and($objEstablishment->feedbacks()->analyzed()->count())->toBe(1)
            ->and(Feedback::query()->visibleTo($objOwner)->count())->toBe(1);
    }
});

test('the sentiment lexicon seeder is idempotent and contains the required tourism words', function () {
    $this->seed(SentimentLexiconSeeder::class);
    $intFirstCount = SentimentLexicon::query()->count();
    $this->seed(SentimentLexiconSeeder::class);

    $arrPolarities = SentimentLexicon::getCachedPolarities();

    expect(SentimentLexicon::query()->count())->toBe($intFirstCount)
        ->and(SentimentLexicon::query()->where('slx_weight', '!=', 1)->count())->toBe(0);

    foreach (['beautiful', 'clean', 'amazing', 'friendly', 'safe', 'accessible'] as $strWord) {
        expect($arrPolarities[$strWord] ?? null)->toBe(SentimentLexicon::POLARITY_POSITIVE);
    }

    foreach (['bad', 'poorly', 'crowded', 'expensive', 'dirty', 'unmaintained'] as $strWord) {
        expect($arrPolarities[$strWord] ?? null)->toBe(SentimentLexicon::POLARITY_NEGATIVE);
    }

    // Words in the documented examples that must stay neutral.
    foreach (['the', 'is', 'and', 'but', 'very', 'view', 'staff', 'place', 'food', 'experience', 'maintained', 'beach'] as $strWord) {
        expect(array_key_exists($strWord, $arrPolarities))->toBeFalse();
    }
});

test('lexicon words are stored lowercase and edits clear the cached lexicon', function () {
    $this->seed(SentimentLexiconSeeder::class);
    expect(SentimentLexicon::getCachedPolarities())->not->toHaveKey('majestic');

    SentimentLexicon::query()->create(['slx_word' => '  Majestic ', 'slx_polarity' => SentimentLexicon::POLARITY_POSITIVE]);

    expect(SentimentLexicon::getCachedPolarities())->toHaveKey('majestic');

    SentimentLexicon::query()->where('slx_word', 'majestic')->first()->delete();

    expect(SentimentLexicon::getCachedPolarities())->not->toHaveKey('majestic');
});

test('the issue lexicon seeder is idempotent and maps the documented keywords and phrases', function () {
    $this->seed(FeedbackIssueLexiconSeeder::class);
    $intFirstCount = FeedbackIssueLexicon::query()->count();
    $this->seed(FeedbackIssueLexiconSeeder::class);

    $arrKeywords = FeedbackIssueLexicon::getCachedKeywords();

    expect(FeedbackIssueLexicon::query()->count())->toBe($intFirstCount)
        ->and($arrKeywords['dirty'])->toBe('Cleanliness')
        ->and($arrKeywords['garbage'])->toBe('Cleanliness')
        ->and($arrKeywords['poorly maintained'])->toBe('Maintenance')
        ->and($arrKeywords['broken'])->toBe('Maintenance')
        ->and($arrKeywords['too many people'])->toBe('Crowd Management')
        ->and($arrKeywords['overpriced'])->toBe('Pricing')
        ->and($arrKeywords['rude'])->toBe('Customer Service')
        ->and($arrKeywords['difficult to access'])->toBe('Accessibility')
        ->and($arrKeywords['unsafe'])->toBe('Safety');

    // Every seeded category is a configured one, and every configured
    // category except Other has at least one keyword.
    $arrConfigured = array_keys(config('tourist_feedback.issue_categories'));
    $arrSeeded = array_values(array_unique($arrKeywords));

    expect(array_diff($arrSeeded, $arrConfigured))->toBe([])
        ->and(array_values(array_diff($arrConfigured, $arrSeeded)))->toBe(['Other']);
});

test('every issue category has its predefined recommendation and Other has none', function () {
    $arrRecommendations = config('tourist_feedback.issue_categories');

    expect($arrRecommendations)->toHaveCount(12)
        ->and($arrRecommendations[config('tourist_feedback.other_issue_category')])->toBeNull()
        ->and($arrRecommendations['Information'])->toBe('Improve visitor information, signage, and posted schedules.')
        ->and($arrRecommendations['Environment'])->toBe('Strengthen environmental protection and natural-site conservation measures.')
        ->and($arrRecommendations['Cleanliness'])->toBe('Improve cleanliness monitoring and waste management.')
        ->and(collect($arrRecommendations)->except('Other')->filter()->count())->toBe(11)
        ->and(config('tourist_feedback.minimum_sample'))->toBe(5);
});
