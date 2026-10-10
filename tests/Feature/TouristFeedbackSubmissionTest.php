<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — Objective 4 Phase 4: the public tourist feedback form
 * and submission (eligibility, validation, consent, Turnstile, honeypot,
 * rate limits, duplicates, processing after the response) and that public
 * pages never reveal analysis results. Turnstile and the translation
 * provider are always faked; no real key is needed.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\FeedbackAnalysisStatus;
use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Exceptions\TranslationFailedException;
use App\Jobs\ProcessFeedbackAnalysis;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Services\FeedbackSubmissionService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FeedbackIssueLexiconSeeder;
use Database\Seeders\SentimentLexiconSeeder;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    $this->seed(SentimentLexiconSeeder::class);
    $this->seed(FeedbackIssueLexiconSeeder::class);
    config([
        'services.openai.api_key' => 'sk-test-not-a-real-key',
        'tourist_feedback.translation.retry_delays_ms' => [0, 0],
    ]);
    Http::preventStrayRequests();

    // One fake for the whole test (Http::fake() stubs stack and the first
    // match wins), answering from settings each test may change through
    // submissionFakeServices().
    submissionFakeServices();
    Http::fake(function (HttpClientRequest $objRequest) {
        if (str_contains($objRequest->url(), 'challenges.cloudflare.com')) {
            return Http::response(['success' => test()->blnTurnstilePasses]);
        }

        $arrReply = test()->arrTranslationReply;

        return $arrReply === null
            ? Http::response(['error' => ['message' => 'unavailable']], 500)
            : Http::response($arrReply);
    });
});

/**
 * Whether Turnstile passes, and the translation provider's reply (null:
 * the provider fails with a server error).
 *
 * @param  array<string, mixed>|null  $arrTranslationReply
 */
function submissionFakeServices(bool $blnTurnstilePasses = true, ?array $arrTranslationReply = null): void
{
    test()->blnTurnstilePasses = $blnTurnstilePasses;
    test()->arrTranslationReply = $arrTranslationReply;
}

/**
 * @param  array<string, mixed>  $arrOverrides
 */
function submissionListing(string $strKind = 'destination', string $strStatus = '', array $arrOverrides = []): Listing
{
    $objMunicipality = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $blnIsDestination = $strKind === 'destination';
    $objCategory = Category::query()->where('cat_name', $arrOverrides['category'] ?? ($blnIsDestination ? 'Tourist Destinations' : 'Accommodation'))->firstOrFail();
    unset($arrOverrides['category']);

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug(($blnIsDestination ? 'sunrise-cove-' : 'harbor-inn-').Str::random(6)),
        'lst_name' => $blnIsDestination ? 'Sunrise Cove' : 'Harbor Inn',
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_type' => $blnIsDestination ? 'Beach' : 'Resort',
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Dahican',
        'lst_status' => $strStatus !== '' ? $strStatus : ($blnIsDestination ? 'Active' : 'PUBLISHED'),
    ], $arrOverrides));
}

/**
 * A complete, valid form post for the listing.
 *
 * @param  array<string, mixed>  $arrOverrides
 * @return array<string, mixed>
 */
function submissionPayload(Listing $objListing, array $arrOverrides = []): array
{
    return array_merge([
        'listing' => $objListing->lst_slug,
        'feedback' => 'The beach is clean and beautiful',
        'tourist_name' => '',
        'visit_date' => '',
        'consent' => '1',
        'website' => '',
        'cf-turnstile-response' => 'turnstile-token',
    ], $arrOverrides);
}

test('the form lists only listings that accept feedback, by public slug, grouped by category', function () {
    $objDestination = submissionListing('destination', 'Active', ['lst_name' => 'Open Falls']);
    $objEstablishment = submissionListing('establishment', 'PUBLISHED', ['lst_name' => 'Open Resort']);
    submissionListing('destination', 'DRAFT', ['lst_name' => 'Draft Falls']);
    submissionListing('establishment', 'UNPUBLISHED', ['lst_name' => 'Unpublished Resort']);
    submissionListing('establishment', 'Suspended', ['lst_name' => 'Suspended Resort']);
    submissionListing('destination', 'Archived', ['lst_name' => 'Archived Falls']);
    submissionListing('establishment', 'PUBLISHED', ['lst_name' => 'Guide Juan', 'category' => 'Travel & Tours', 'lst_type' => config('establishment_categories.tour_guide_type')]);

    $this->get(route('feedback.create'))
        ->assertOk()
        ->assertSee('<optgroup label="Tourist Destinations">', false)
        ->assertSee('<optgroup label="Accommodation">', false)
        ->assertSee('value="'.$objDestination->lst_slug.'"', false)
        ->assertSee('value="'.$objEstablishment->lst_slug.'"', false)
        ->assertSee('Open Falls')
        ->assertSee('Open Resort')
        ->assertDontSee('Draft Falls')
        ->assertDontSee('Unpublished Resort')
        ->assertDontSee('Suspended Resort')
        ->assertDontSee('Archived Falls')
        ->assertDontSee('Guide Juan')
        ->assertDontSee($objDestination->lst_uuid)
        ->assertDontSee($objEstablishment->lst_uuid);
});

test('the form carries CSRF, Turnstile, the honeypot, the consent wording, and noindex', function () {
    config(['services.turnstile.site_key' => 'site-key-123', 'services.turnstile.secret_key' => 'secret-key-456']);

    $this->get(route('feedback.create'))
        ->assertOk()
        ->assertSee('name="_token"', false)
        ->assertSee('data-sitekey="site-key-123"', false)
        ->assertDontSee('secret-key-456')
        ->assertSee('name="website"', false)
        ->assertSee('external translation service')
        ->assertSee('tourism analysis')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('a listing slug in the URL preselects it; an ineligible one shows a notice instead', function () {
    $objListing = submissionListing('establishment', 'PUBLISHED', ['lst_name' => 'Preselected Inn']);
    $objDraft = submissionListing('destination', 'DRAFT');

    $this->get(route('feedback.create', ['listing' => $objListing->lst_slug]))
        ->assertOk()
        ->assertSee('Tell us about your visit to')
        ->assertSee('value="'.$objListing->lst_slug.'" selected', false);

    $this->get(route('feedback.create', ['listing' => $objDraft->lst_slug]))
        ->assertOk()
        ->assertSee("That place isn't accepting feedback right now.", false);
});

test('eligible detail pages link to the form by slug; tour guides and staff previews do not', function () {
    $objDestination = submissionListing('destination');
    $objEstablishment = submissionListing('establishment');
    $objTourGuide = submissionListing('establishment', 'PUBLISHED', ['category' => 'Travel & Tours', 'lst_type' => config('establishment_categories.tour_guide_type')]);
    $objDraft = submissionListing('destination', 'DRAFT');
    $strShareLink = fn (Listing $objListing) => 'href="'.e(route('feedback.create', ['listing' => $objListing->lst_slug])).'"';

    $this->get(route('listings.show', $objDestination))->assertOk()->assertSee('Share Your Experience')->assertSee($strShareLink($objDestination), false);
    $this->get(route('listings.show', $objEstablishment))->assertOk()->assertSee($strShareLink($objEstablishment), false);
    $this->get(route('listings.show', $objTourGuide))->assertOk()->assertDontSee('Share Your Experience');

    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $this->actingAs($objPto)->get(route('listings.show', $objDraft))->assertOk()->assertDontSee('Share Your Experience');
});

test('a valid submission is stored with consent, processed after the response, and answered with a neutral thank-you', function () {
    $objListing = submissionListing('destination');

    $this->post(route('feedback.store'), submissionPayload($objListing, [
        'tourist_name' => '  Ana  ',
        'visit_date' => today()->toDateString(),
    ]))->assertRedirect(route('feedback.thankYou'));

    $objFeedback = Feedback::query()->sole();

    expect($objFeedback->lst_id)->toBe($objListing->lst_id)
        ->and($objFeedback->fbk_original_text)->toBe('The beach is clean and beautiful')
        ->and($objFeedback->fbk_tourist_name)->toBe('Ana')
        ->and($objFeedback->fbk_visit_date->toDateString())->toBe(today()->toDateString())
        ->and($objFeedback->fbk_consent_at)->not->toBeNull()
        ->and($objFeedback->fbk_content_hash)->toHaveLength(64)
        // The after-response job already ran the deterministic analysis.
        ->and($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Analyzed);

    $this->get(route('feedback.thankYou'))
        ->assertOk()
        ->assertSee('Thank you for sharing your experience!')
        ->assertSee('Your feedback has been recorded.')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('the analysis job is dispatched after the response, never during the request', function () {
    Bus::fake();
    $objListing = submissionListing('establishment');

    $this->post(route('feedback.store'), submissionPayload($objListing))->assertRedirect(route('feedback.thankYou'));

    Bus::assertDispatchedAfterResponse(ProcessFeedbackAnalysis::class, fn (ProcessFeedbackAnalysis $objJob) => $objJob->intFeedbackId === Feedback::query()->sole()->fbk_id);
    expect(Feedback::query()->sole()->fbk_status)->toBe(FeedbackAnalysisStatus::Pending);
});

test('public responses never reveal a score, class, issue, recommendation, or translation', function () {
    submissionFakeServices(true, ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode([
        'language' => 'tl',
        'translation' => 'The bathroom is dirty and the staff are rude.',
    ])]]]]);
    $objListing = submissionListing('establishment');

    $objStoreResponse = $this->post(route('feedback.store'), submissionPayload($objListing, ['feedback' => 'Ang dumi ng banyo at ang bastos ng staff.']));
    $objThankYou = $this->get(route('feedback.thankYou'));

    expect(Feedback::query()->sole()->fbk_status)->toBe(FeedbackAnalysisStatus::Analyzed);

    foreach ([$objStoreResponse, $objThankYou] as $objResponse) {
        foreach (['Negative', 'negative', 'Cleanliness', 'Customer Service', 'Improve', 'score', 'The bathroom is dirty'] as $strHidden) {
            $objResponse->assertDontSee($strHidden);
        }
    }

    expect(session()->all())->not->toHaveKey('sentiment');
});

test('a translation failure leaves the feedback failed with no fabricated result, and the tourist still sees the thank-you', function () {
    submissionFakeServices(true, null);
    $objListing = submissionListing('destination');

    $this->post(route('feedback.store'), submissionPayload($objListing, ['feedback' => 'Nindot kaayo ang place pero crowded.']))
        ->assertRedirect(route('feedback.thankYou'));

    $objFeedback = Feedback::query()->sole();

    expect($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Failed)
        ->and($objFeedback->fbk_failure_reason)->toBe(TranslationFailedException::REQUEST_FAILED)
        ->and($objFeedback->fbk_sentiment)->toBeNull()
        ->and($objFeedback->fbk_sentiment_score)->toBeNull()
        ->and($objFeedback->fbk_translated_text)->toBeNull()
        ->and($objFeedback->fbk_original_text)->toBe('Nindot kaayo ang place pero crowded.');
});

test('an unknown, unpublished, or tour-guide listing is refused and nothing is stored', function (string $strCase) {
    $objListing = match ($strCase) {
        'draft destination' => submissionListing('destination', 'DRAFT'),
        'suspended destination' => submissionListing('destination', 'Suspended'),
        'unpublished establishment' => submissionListing('establishment', 'UNPUBLISHED'),
        'archived establishment' => submissionListing('establishment', 'Archived'),
        'tour guide' => submissionListing('establishment', 'PUBLISHED', ['category' => 'Travel & Tours', 'lst_type' => config('establishment_categories.tour_guide_type')]),
        default => null,
    };
    $arrPayload = $objListing ? submissionPayload($objListing) : submissionPayload(submissionListing(), ['listing' => 'no-such-place']);

    $this->from(route('feedback.create'))->post(route('feedback.store'), $arrPayload)
        ->assertRedirect(route('feedback.create'))
        ->assertSessionHasErrors(['listing' => 'Please choose a destination or establishment from the list.']);

    expect(Feedback::query()->count())->toBe(0);
})->with(['unknown slug', 'draft destination', 'suspended destination', 'unpublished establishment', 'archived establishment', 'tour guide']);

test('a listing id or QR uuid is never accepted in place of the slug', function () {
    $objListing = submissionListing('establishment');

    foreach ([(string) $objListing->lst_id, $objListing->lst_uuid] as $strIdentifier) {
        $this->post(route('feedback.store'), submissionPayload($objListing, ['listing' => $strIdentifier]))->assertSessionHasErrors('listing');
    }

    expect(Feedback::query()->count())->toBe(0);
});

test('consent is required', function () {
    $objListing = submissionListing();

    $this->post(route('feedback.store'), submissionPayload($objListing, ['consent' => '']))
        ->assertSessionHasErrors(['consent' => 'Please agree to the consent statement to submit your feedback.']);

    expect(Feedback::query()->count())->toBe(0);
});

test('feedback, name, and visit date are validated on the server', function (array $arrOverrides, string $strField) {
    $this->post(route('feedback.store'), submissionPayload(submissionListing(), $arrOverrides))->assertSessionHasErrors($strField);

    expect(Feedback::query()->count())->toBe(0);
})->with([
    'missing feedback' => [['feedback' => ''], 'feedback'],
    'too short' => [['feedback' => 'Nice'], 'feedback'],
    'too long' => [['feedback' => str_repeat('a', 1001)], 'feedback'],
    'name too long' => [['tourist_name' => str_repeat('n', 101)], 'tourist_name'],
    // A closure so "tomorrow" is computed when the test runs, after Laravel
    // has set the Asia/Manila timezone — not at file load time in the PHP
    // CLI's UTC, where it equals today in Manila between 00:00 and 08:00.
    'future visit date' => [fn () => ['visit_date' => today()->addDay()->toDateString()], 'visit_date'],
    'invalid visit date' => [['visit_date' => '2026-02-30'], 'visit_date'],
    'missing listing' => [['listing' => ''], 'listing'],
]);

test('the name and visit date are optional', function () {
    $this->post(route('feedback.store'), submissionPayload(submissionListing()))->assertRedirect(route('feedback.thankYou'));

    $objFeedback = Feedback::query()->sole();

    expect($objFeedback->fbk_tourist_name)->toBeNull()
        ->and($objFeedback->fbk_visit_date)->toBeNull();
});

test('a failed or missing Turnstile check is refused and nothing is stored', function () {
    $objListing = submissionListing();
    submissionFakeServices(false);

    $this->post(route('feedback.store'), submissionPayload($objListing))
        ->assertSessionHasErrors(['cf-turnstile-response' => 'Verification check failed. Please try again.']);
    $this->post(route('feedback.store'), submissionPayload($objListing, ['cf-turnstile-response' => '']))
        ->assertSessionHasErrors(['cf-turnstile-response' => 'Please complete the verification check.']);

    expect(Feedback::query()->count())->toBe(0);
    Http::assertSent(fn ($objRequest) => str_contains($objRequest->url(), 'challenges.cloudflare.com'));
});

test('a filled honeypot is stored as rejected, never analyzed, and still sees the thank-you', function () {
    Bus::fake();

    $this->post(route('feedback.store'), submissionPayload(submissionListing(), ['website' => 'http://spam.example']))
        ->assertRedirect(route('feedback.thankYou'));

    $objFeedback = Feedback::query()->sole();

    expect($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Rejected)
        ->and($objFeedback->fbk_failure_reason)->toBe(FeedbackSubmissionService::REASON_HONEYPOT)
        ->and(Feedback::query()->analyzed()->count())->toBe(0);
    Bus::assertNotDispatchedAfterResponse(ProcessFeedbackAnalysis::class);
});

test('the same feedback for the same listing within the window is stored once; other listings and later posts are not duplicates', function () {
    Bus::fake();
    $objListing = submissionListing();
    $objOtherListing = submissionListing('establishment');

    $this->post(route('feedback.store'), submissionPayload($objListing))->assertRedirect(route('feedback.thankYou'));
    // Case, punctuation, and spacing do not defeat the duplicate check.
    $this->post(route('feedback.store'), submissionPayload($objListing, ['feedback' => 'THE beach is clean,   and beautiful!']))->assertRedirect(route('feedback.thankYou'));
    $this->post(route('feedback.store'), submissionPayload($objOtherListing))->assertRedirect(route('feedback.thankYou'));

    expect(Feedback::query()->where('lst_id', $objListing->lst_id)->count())->toBe(1)
        ->and(Feedback::query()->where('lst_id', $objOtherListing->lst_id)->count())->toBe(1);
    Bus::assertDispatchedAfterResponseTimes(ProcessFeedbackAnalysis::class, 2);

    $this->travel((int) config('tourist_feedback.duplicate_window_minutes') + 1)->minutes();
    $this->post(route('feedback.store'), submissionPayload($objListing))->assertRedirect(route('feedback.thankYou'));

    expect(Feedback::query()->where('lst_id', $objListing->lst_id)->count())->toBe(2);
});

test('five completed submissions per hour per IP are allowed, the sixth is throttled, and the limit recovers', function () {
    Bus::fake();
    $objListing = submissionListing();

    foreach (range(1, 5) as $intAttempt) {
        $this->post(route('feedback.store'), submissionPayload($objListing, ['feedback' => "Lovely beach visit number {$intAttempt}"]))
            ->assertRedirect(route('feedback.thankYou'));
    }

    $this->post(route('feedback.store'), submissionPayload($objListing, ['feedback' => 'Lovely beach visit number 6']))->assertStatus(429);

    $this->travel(61)->minutes();

    $this->post(route('feedback.store'), submissionPayload($objListing, ['feedback' => 'Lovely beach visit number 7']))->assertRedirect(route('feedback.thankYou'));
    expect(Feedback::query()->count())->toBe(6);
});

test('rejected attempts do not use up the hourly submission limit', function () {
    Bus::fake();
    $objListing = submissionListing();

    foreach (range(1, 6) as $intAttempt) {
        $this->post(route('feedback.store'), submissionPayload($objListing, ['consent' => '']))->assertSessionHasErrors('consent');
    }

    $this->post(route('feedback.store'), submissionPayload($objListing))->assertRedirect(route('feedback.thankYou'));
});

test('submitted text redisplayed after a validation error is escaped', function () {
    $objListing = submissionListing();
    $strScript = '<script>alert("x")</script> lovely beach';

    $this->from(route('feedback.create'))
        ->post(route('feedback.store'), submissionPayload($objListing, ['feedback' => $strScript, 'consent' => '']));

    $this->get(route('feedback.create'))
        ->assertOk()
        ->assertDontSee($strScript, false)
        ->assertSee(e($strScript), false);
});

test('historical feedback stays when a listing is unpublished, and new feedback is refused', function () {
    Bus::fake();
    $objListing = submissionListing('establishment');

    $this->post(route('feedback.store'), submissionPayload($objListing))->assertRedirect(route('feedback.thankYou'));
    $objListing->forceFill(['lst_status' => 'UNPUBLISHED'])->save();

    $this->post(route('feedback.store'), submissionPayload($objListing, ['feedback' => 'A different later visit here']))->assertSessionHasErrors('listing');

    expect($objListing->feedbacks()->count())->toBe(1);
});

/**
 * An establishment that accepts QR check-ins: Online iTOUR reporting and an
 * active linked account (QR eligibility does not depend on publication).
 */
function submissionQrEstablishment(string $strStatus): Listing
{
    $objListing = submissionListing('establishment', $strStatus, ['lst_name' => 'QR Harbor Inn']);
    $objListing->forceFill(['lst_reporting_mode' => ReportingMethod::OnlineItour])->save();
    User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => $objListing->lst_name,
        'lst_id' => $objListing->lst_id,
        'mun_id' => $objListing->mun_id,
    ]);

    return $objListing->fresh();
}

test('the QR check-in success step offers Share Your Experience by slug for a published establishment', function () {
    $objListing = submissionQrEstablishment('PUBLISHED');
    $strFeedbackUrl = route('feedback.create', ['listing' => $objListing->lst_slug]);

    expect($objListing->isAcceptingRegistrations())->toBeTrue();

    $objResponse = $this->get(route('lgu.establishmentQr', $objListing->lst_uuid))
        ->assertOk()
        ->assertSee('Share Your Experience')
        ->assertSee('href="'.e($strFeedbackUrl).'"', false);

    // The link carries the public slug only — never the id or the QR uuid.
    expect($strFeedbackUrl)->toBe(url('/feedback').'?listing='.$objListing->lst_slug)
        ->and($strFeedbackUrl)->not->toContain($objListing->lst_uuid)
        ->and(substr_count($objResponse->getContent(), 'Share Your Experience'))->toBe(1);
});

test('the QR check-in page shows no feedback link when the establishment is not published', function () {
    $objListing = submissionQrEstablishment('DRAFT');

    expect($objListing->isAcceptingRegistrations())->toBeTrue();

    $this->get(route('lgu.establishmentQr', $objListing->lst_uuid))
        ->assertOk()
        ->assertSee('Visitor Registration')
        ->assertDontSee('Share Your Experience');
});

test('the footer links to the feedback form and the fabricated landing reviews are gone', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.route('feedback.create').'"', false)
        ->assertDontSee('Stories from the road')
        ->assertDontSee('id="reviews"', false);
});
