<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — Objective 4 Phase 5: the authorized feedback and
 * analytics pages of the PTO, LGU, and Establishment roles (role
 * boundaries, cross-municipality 403 with security logging, establishment
 * ownership, empty data, processing statuses, recommendations, escaping,
 * dashboards) and that none of it is public. No external service is called.
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
use App\Models\SecurityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    Http::preventStrayRequests();
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));

    $this->objMati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $this->objBaganga = Municipality::query()->firstOrCreate(['mun_code' => 'BAGANGA'], ['mun_name' => 'Baganga']);

    $this->objMatiFalls = pagesListing($this->objMati, 'destination', 'Mati Falls');
    $this->objMatiInn = pagesListing($this->objMati, 'establishment', 'Mati Harbor Inn');
    $this->objOtherMatiInn = pagesListing($this->objMati, 'establishment', 'Other Mati Lodge');
    $this->objBagangaBeach = pagesListing($this->objBaganga, 'destination', 'Baganga Beach');

    pagesFeedback($this->objMatiFalls, 'Mati falls feedback text', SentimentClassification::Positive, 0.4);
    pagesFeedback($this->objMatiInn, 'Mati inn feedback text', SentimentClassification::Negative, -0.4, ['Cleanliness']);
    pagesFeedback($this->objOtherMatiInn, 'Other lodge feedback text', SentimentClassification::Neutral, 0.0);
    pagesFeedback($this->objBagangaBeach, 'Baganga beach feedback text', SentimentClassification::Positive, 0.5);

    $this->objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $this->objMatiLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'usr_organization_subtitle' => 'City of Mati', 'mun_id' => $this->objMati->mun_id]);
    $this->objOwner = User::factory()->create(['usr_role' => UserRole::Establishment, 'usr_organization_name' => 'Mati Harbor Inn', 'mun_id' => $this->objMati->mun_id, 'lst_id' => $this->objMatiInn->lst_id]);
});

function pagesListing(Municipality $objMunicipality, string $strKind, string $strName): Listing
{
    $blnIsDestination = $strKind === 'destination';
    $objCategory = Category::query()->where('cat_name', $blnIsDestination ? 'Tourist Destinations' : 'Accommodation')->firstOrFail();

    return Listing::query()->create([
        'lst_slug' => Str::slug($strName.'-'.Str::random(5)),
        'lst_name' => $strName,
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => $blnIsDestination ? 'Active' : 'PUBLISHED',
    ]);
}

/**
 * An analyzed feedback row (or, with $objClass null, a row in another status).
 *
 * @param  array<int, string>  $arrIssues
 * @param  array<string, mixed>  $arrExtra
 */
function pagesFeedback(Listing $objListing, string $strText, ?SentimentClassification $objClass, float $fltScore = 0.0, array $arrIssues = [], array $arrExtra = []): Feedback
{
    $objFeedback = $objListing->feedbacks()->make(['fbk_original_text' => $strText]);
    $arrAnalysis = $objClass === null ? [] : [
        'fbk_status' => FeedbackAnalysisStatus::Analyzed,
        'fbk_sentiment' => $objClass,
        'fbk_sentiment_score' => $fltScore,
        'fbk_translated_text' => $strText,
        'fbk_detected_language' => 'en',
        'fbk_analyzed_at' => now(),
    ];
    $objFeedback->forceFill(array_merge([
        'fbk_consent_at' => now(),
        'fbk_content_hash' => hash('sha256', $strText.Str::random(4)),
    ], $arrAnalysis, $arrExtra))->save();

    foreach ($arrIssues as $strCategory) {
        $objFeedback->issues()->create(['fbi_issue_category' => $strCategory, 'fbi_matched_keyword' => null]);
    }

    return $objFeedback;
}

test('guests are sent to login and the public never reaches analytics', function (string $strRoute) {
    $this->get(route($strRoute))->assertRedirect(route('login'));
})->with(['pto.feedback.index', 'pto.feedback.analytics', 'lgu.feedback.index', 'lgu.feedback.analytics', 'establishment.feedback.index', 'establishment.feedback.analytics']);

test('guests cannot open a listing report', function () {
    $this->get(route('pto.feedback.listing', $this->objMatiFalls))->assertRedirect(route('login'));
    $this->get(route('lgu.feedback.listing', $this->objMatiFalls))->assertRedirect(route('login'));
});

test('each role is refused the other roles\' feedback pages', function () {
    $this->actingAs($this->objMatiLgu)->get(route('pto.feedback.analytics'))->assertForbidden();
    $this->actingAs($this->objOwner)->get(route('lgu.feedback.analytics'))->assertForbidden();
    $this->actingAs($this->objOwner)->get(route('pto.feedback.listing', $this->objMatiInn))->assertForbidden();
    $this->actingAs($this->objPto)->get(route('establishment.feedback.index'))->assertForbidden();
});

test('the PTO sees province-wide feedback, analytics, and any listing report', function () {
    $this->actingAs($this->objPto)->get(route('pto.feedback.index'))
        ->assertOk()
        ->assertSee('Mati falls feedback text')
        ->assertSee('Baganga beach feedback text')
        ->assertSee('Other lodge feedback text');

    $this->actingAs($this->objPto)->get(route('pto.feedback.analytics'))
        ->assertOk()
        ->assertSee('Sentiment by Destination')
        ->assertSee('Sentiment by Establishment')
        ->assertSee('Baganga Beach')
        ->assertSee('Mati Harbor Inn')
        ->assertSee(route('pto.feedback.listing', $this->objBagangaBeach), false);

    $this->actingAs($this->objPto)->get(route('pto.feedback.listing', $this->objBagangaBeach))
        ->assertOk()
        ->assertSee('Baganga Beach')
        ->assertSee('Baganga beach feedback text');
});

test('the LGU sees only its own municipality', function () {
    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.index'))
        ->assertOk()
        ->assertSee('Mati falls feedback text')
        ->assertSee('Mati inn feedback text')
        ->assertDontSee('Baganga beach feedback text');

    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.analytics'))
        ->assertOk()
        ->assertSee('Mati Falls')
        ->assertDontSee('Baganga Beach');

    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.listing', $this->objMatiInn))
        ->assertOk()
        ->assertSee('Mati inn feedback text');
});

test('an LGU opening another municipality\'s listing report gets 403 and the attempt is logged', function () {
    $intLogsBefore = SecurityLog::query()->count();

    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.listing', $this->objBagangaBeach))->assertForbidden();

    expect(SecurityLog::query()->count())->toBe($intLogsBefore + 1);
});

test('request input cannot widen the LGU scope', function () {
    $this->actingAs($this->objMatiLgu)
        ->get(route('lgu.feedback.index', ['mun_id' => $this->objBaganga->mun_id, 'municipality' => 'Baganga', 'listing' => $this->objBagangaBeach->lst_slug]))
        ->assertOk()
        ->assertDontSee('Baganga beach feedback text');
});

test('an establishment owner sees only its own listing — not other establishments or destinations in its municipality', function () {
    $this->actingAs($this->objOwner)->get(route('establishment.feedback.index'))
        ->assertOk()
        ->assertSee('Mati inn feedback text')
        ->assertDontSee('Mati falls feedback text')
        ->assertDontSee('Other lodge feedback text')
        ->assertDontSee('Baganga beach feedback text');

    $this->actingAs($this->objOwner)->get(route('establishment.feedback.analytics'))
        ->assertOk()
        ->assertSee('Mati Harbor Inn')
        ->assertSee('Cleanliness');
});

test('an establishment account with no linked listing sees no feedback at all', function () {
    $objUnlinked = User::factory()->create(['usr_role' => UserRole::Establishment, 'mun_id' => $this->objMati->mun_id, 'lst_id' => null]);

    $this->actingAs($objUnlinked)->get(route('establishment.feedback.index'))
        ->assertOk()
        ->assertSee('No establishment linked')
        ->assertDontSee('feedback text');
    $this->actingAs($objUnlinked)->get(route('establishment.feedback.analytics'))
        ->assertOk()
        ->assertSee('No establishment linked');
});

test('pending, failed, and rejected feedback is labeled and excluded from sentiment results', function () {
    pagesFeedback($this->objMatiFalls, 'Still pending text', null);
    pagesFeedback($this->objMatiFalls, 'Failed translation text', null, 0.0, [], ['fbk_status' => FeedbackAnalysisStatus::Failed, 'fbk_failure_reason' => 'Translation request failed.']);
    pagesFeedback($this->objMatiFalls, 'Rejected honeypot text', null, 0.0, [], ['fbk_status' => FeedbackAnalysisStatus::Rejected, 'fbk_failure_reason' => 'Automated submission (honeypot field filled).']);

    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.index'))
        ->assertOk()
        ->assertSee('Failed translation text')
        ->assertSee('Not analyzed:')
        ->assertSee('Translation request failed.')
        ->assertSee('Only analyzed feedback is included in sentiment results.');

    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.index', ['status' => 'failed']))
        ->assertOk()
        ->assertSee('Failed translation text')
        ->assertDontSee('Still pending text')
        ->assertDontSee('Mati falls feedback text');

    // Mati: 3 analyzed (falls, inn, lodge) out of 6 submissions.
    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.analytics'))
        ->assertOk()
        ->assertSee('6 submissions in this period.')
        ->assertSee('3 analyzed reviews');
});

test('a period with no feedback shows an empty state instead of charts', function () {
    $this->actingAs($this->objPto)->get(route('pto.feedback.analytics', ['period' => 'last_month']))
        ->assertOk()
        ->assertSee('No feedback in this period')
        ->assertDontSee('Overall Sentiment');
});

test('suggested improvements appear only when negative feedback dominates with the minimum sample', function () {
    foreach (range(1, 4) as $intIndex) {
        pagesFeedback($this->objMatiInn, "Dirty room complaint {$intIndex}", SentimentClassification::Negative, -0.3, ['Cleanliness']);
    }

    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.listing', $this->objMatiInn))
        ->assertOk()
        ->assertSee('Improve cleanliness monitoring and waste management.');

    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.listing', $this->objMatiFalls))
        ->assertOk()
        ->assertSee('None yet')
        ->assertDontSee('Improve cleanliness monitoring and waste management.');
});

test('feedback text and tourist names are escaped on every analytics page', function () {
    pagesFeedback($this->objMatiInn, '<script>alert("x")</script> broken', SentimentClassification::Negative, -0.5, [], ['fbk_tourist_name' => '<b>Eve</b>']);

    $this->actingAs($this->objOwner)->get(route('establishment.feedback.index'))
        ->assertOk()
        ->assertDontSee('<script>alert("x")</script>', false)
        ->assertSee(e('<script>alert("x")</script>'), false)
        ->assertDontSee('<b>Eve</b>', false);
});

test('the period filter on the list uses the submission date', function () {
    pagesFeedback($this->objMatiFalls, 'Old September feedback', SentimentClassification::Positive, 0.5, [], ['fbk_created_at' => '2026-09-10 08:00:00']);

    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.index', ['period' => 'this_month']))
        ->assertOk()
        ->assertSee('Mati falls feedback text')
        ->assertDontSee('Old September feedback');
    $this->actingAs($this->objMatiLgu)->get(route('lgu.feedback.index', ['period' => 'last_month']))
        ->assertOk()
        ->assertSee('Old September feedback');
});

test('dashboards show real feedback numbers for the user\'s own scope', function () {
    $this->actingAs($this->objMatiLgu)->get(route('lgu.dashboard'))
        ->assertOk()
        ->assertSee('3 feedback entries');

    $this->actingAs($this->objOwner)->get(route('establishment.dashboard'))
        ->assertOk()
        ->assertSee('1 feedback entries')
        ->assertSee('0% Positive');
});

test('public pages never show analytics or internal classifications', function () {
    $this->get(route('listings.show', $this->objMatiInn))
        ->assertOk()
        ->assertDontSee('Mati inn feedback text')
        ->assertDontSee('Cleanliness')
        ->assertDontSee('Sentiment');
});
