<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — Objective 4 Phase 3: the feedback processing pipeline
 * (English shortcut, translation, lexicon sentiment on the English text,
 * issue rows for negative feedback, failed and rejected outcomes,
 * reprocessing), the local English check, the after-response job, and the
 * feedback:reprocess command. The translation provider is always faked.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\FeedbackAnalysisStatus;
use App\Enums\SentimentClassification;
use App\Exceptions\TranslationFailedException;
use App\Jobs\ProcessFeedbackAnalysis;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\Listing;
use App\Models\Municipality;
use App\Services\FeedbackAnalysisService;
use App\Support\EnglishTextDetector;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FeedbackIssueLexiconSeeder;
use Database\Seeders\SentimentLexiconSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(SentimentLexiconSeeder::class);
    $this->seed(FeedbackIssueLexiconSeeder::class);
    config([
        'services.openai.api_key' => 'sk-test-not-a-real-key',
        'tourist_feedback.translation.retry_delays_ms' => [0, 0],
    ]);
    Http::preventStrayRequests();
});

function pipelineListing(): Listing
{
    test()->seed(CategorySeeder::class);
    $objMunicipality = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $objCategory = Category::query()->where('cat_name', 'Tourist Destinations')->firstOrFail();

    return Listing::query()->create([
        'lst_slug' => Str::slug('pipeline-beach-'.Str::random(6)),
        'lst_name' => 'Pipeline Beach',
        'lst_category' => 'destinations',
        'cat_id' => $objCategory->cat_id,
        'lst_type' => 'Beach',
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Dahican',
        'lst_status' => 'Active',
    ]);
}

/**
 * A stored feedback row as the public form will save it (pending).
 *
 * @param  array<string, mixed>  $arrSystemFields
 */
function pipelineFeedback(string $strText, array $arrSystemFields = []): Feedback
{
    $objFeedback = pipelineListing()->feedbacks()->make(['fbk_original_text' => $strText]);
    $objFeedback->forceFill(array_merge([
        'fbk_consent_at' => now(),
        'fbk_content_hash' => hash('sha256', Str::lower($strText)),
    ], $arrSystemFields))->save();

    return $objFeedback;
}

function pipelineProcess(Feedback $objFeedback): Feedback
{
    return app(FeedbackAnalysisService::class)->process($objFeedback);
}

/**
 * @return array<string, mixed>
 */
function pipelineReply(string $strLanguage, string $strTranslation): array
{
    return ['choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode(['language' => $strLanguage, 'translation' => $strTranslation])]]]];
}

/**
 * The analysis fields a failed or rejected row must never carry.
 */
function pipelineExpectNoAnalysis(Feedback $objFeedback): void
{
    expect($objFeedback->fbk_translated_text)->toBeNull()
        ->and($objFeedback->fbk_detected_language)->toBeNull()
        ->and($objFeedback->fbk_positive_count)->toBeNull()
        ->and($objFeedback->fbk_negative_count)->toBeNull()
        ->and($objFeedback->fbk_total_word_count)->toBeNull()
        ->and($objFeedback->fbk_sentiment_score)->toBeNull()
        ->and($objFeedback->fbk_sentiment)->toBeNull()
        ->and($objFeedback->fbk_matched_terms)->toBeNull()
        ->and($objFeedback->fbk_analyzed_at)->toBeNull()
        ->and($objFeedback->issues()->count())->toBe(0);
}

test('clearly English feedback is analyzed as written without calling the provider', function () {
    Http::fake();
    $objFeedback = pipelineProcess(pipelineFeedback('The beach is clean and beautiful'));

    Http::assertNothingSent();
    expect($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Analyzed)
        ->and($objFeedback->fbk_detected_language)->toBe('en')
        ->and($objFeedback->fbk_original_text)->toBe('The beach is clean and beautiful')
        ->and($objFeedback->fbk_translated_text)->toBe('The beach is clean and beautiful')
        ->and($objFeedback->fbk_positive_count)->toBe(2)
        ->and($objFeedback->fbk_negative_count)->toBe(0)
        ->and($objFeedback->fbk_total_word_count)->toBe(6)
        ->and($objFeedback->fbk_sentiment_score)->toBe('0.3333')
        ->and($objFeedback->fbk_sentiment)->toBe(SentimentClassification::Positive)
        ->and($objFeedback->fbk_matched_terms)->toBe(['positive' => ['clean', 'beautiful'], 'negative' => []])
        ->and($objFeedback->fbk_failure_reason)->toBeNull()
        ->and($objFeedback->fbk_analyzed_at)->not->toBeNull()
        ->and($objFeedback->issues()->count())->toBe(0);
});

test('Cebuano feedback keeps its original, stores the translation, and is scored on the English text', function () {
    Http::fake(['api.openai.com/*' => Http::response(pipelineReply('ceb', 'The place is very beautiful but crowded.'))]);
    $objFeedback = pipelineProcess(pipelineFeedback('Nindot kaayo ang place pero crowded.'));

    Http::assertSentCount(1);
    expect($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Analyzed)
        ->and($objFeedback->fbk_original_text)->toBe('Nindot kaayo ang place pero crowded.')
        ->and($objFeedback->fbk_translated_text)->toBe('The place is very beautiful but crowded.')
        ->and($objFeedback->fbk_detected_language)->toBe('ceb')
        ->and($objFeedback->fbk_positive_count)->toBe(1)
        ->and($objFeedback->fbk_negative_count)->toBe(1)
        ->and($objFeedback->fbk_total_word_count)->toBe(7)
        ->and($objFeedback->fbk_sentiment_score)->toBe('0.0000')
        ->and($objFeedback->fbk_sentiment)->toBe(SentimentClassification::Neutral)
        // Neutral: "crowded" is never turned into an issue.
        ->and($objFeedback->issues()->count())->toBe(0);
});

test('negative Tagalog feedback stores one issue row per detected category with its keyword', function () {
    Http::fake(['api.openai.com/*' => Http::response(pipelineReply('tl', 'The bathroom is dirty and the staff are rude.'))]);
    $objFeedback = pipelineProcess(pipelineFeedback('Ang dumi ng banyo at ang bastos ng staff.'));

    expect($objFeedback->fbk_sentiment)->toBe(SentimentClassification::Negative)
        ->and($objFeedback->fbk_matched_terms['negative'])->toBe(['dirty', 'rude'])
        ->and($objFeedback->issues()->orderBy('fbi_id')->get(['fbi_issue_category', 'fbi_matched_keyword'])->toArray())->toBe([
            ['fbi_issue_category' => 'Cleanliness', 'fbi_matched_keyword' => 'dirty'],
            ['fbi_issue_category' => 'Customer Service', 'fbi_matched_keyword' => 'rude'],
        ]);
});

test('negative feedback without a known issue is stored under Other', function () {
    Http::fake();
    $objFeedback = pipelineProcess(pipelineFeedback('The trip was terrible and boring'));

    expect($objFeedback->fbk_sentiment)->toBe(SentimentClassification::Negative)
        ->and($objFeedback->issues()->pluck('fbi_issue_category')->all())->toBe(['Other'])
        ->and($objFeedback->issues()->value('fbi_matched_keyword'))->toBeNull();
});

test('when the provider reports English, the original is analyzed unchanged', function () {
    Http::fake(['api.openai.com/*' => Http::response(pipelineReply('en', 'Superb snorkeling and a crystal lagoon.'))]);
    $objFeedback = pipelineProcess(pipelineFeedback('Superb snorkeling, crystal lagoon'));

    Http::assertSentCount(1);
    expect($objFeedback->fbk_detected_language)->toBe('en')
        ->and($objFeedback->fbk_translated_text)->toBe('Superb snorkeling, crystal lagoon');
});

test('a translation failure marks the feedback failed and never analyzes the original text', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'down']], 503)]);
    $objFeedback = pipelineProcess(pipelineFeedback('Nindot kaayo ang place pero crowded.'));

    expect($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Failed)
        ->and($objFeedback->fbk_failure_reason)->toBe(TranslationFailedException::REQUEST_FAILED)
        ->and($objFeedback->fbk_original_text)->toBe('Nindot kaayo ang place pero crowded.')
        ->and(Feedback::query()->analyzed()->count())->toBe(0);
    pipelineExpectNoAnalysis($objFeedback);
});

test('empty, invalid, and unconfigured translations are each stored as failed with their reason', function (?array $arrReply, ?string $strApiKey, string $strReason) {
    config(['services.openai.api_key' => $strApiKey]);
    Http::fake($arrReply === null ? [] : ['api.openai.com/*' => Http::response($arrReply)]);

    $objFeedback = pipelineProcess(pipelineFeedback('Ang ganda ng lugar pero mahal ang food'));

    expect($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Failed)
        ->and($objFeedback->fbk_failure_reason)->toBe($strReason);
    pipelineExpectNoAnalysis($objFeedback);
})->with([
    'empty translation' => [pipelineReply('tl', '   '), 'sk-test-not-a-real-key', TranslationFailedException::EMPTY_TRANSLATION],
    'invalid response' => [['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'Sure! Here it is.']]]], 'sk-test-not-a-real-key', TranslationFailedException::INVALID_RESPONSE],
    'no API key' => [null, null, TranslationFailedException::NOT_CONFIGURED],
]);

test('feedback with no words, or longer than the maximum, is rejected without calling the provider', function () {
    Http::fake();
    config(['tourist_feedback.feedback_max_length' => 40]);

    $objNoWords = pipelineProcess(pipelineFeedback('!!! ... 😀'));
    $objTooLong = pipelineProcess(pipelineFeedback(str_repeat('Nindot kaayo! ', 5)));

    Http::assertNothingSent();
    expect($objNoWords->fbk_status)->toBe(FeedbackAnalysisStatus::Rejected)
        ->and($objNoWords->fbk_failure_reason)->toBe(FeedbackAnalysisService::REASON_NO_WORDS)
        ->and($objTooLong->fbk_status)->toBe(FeedbackAnalysisStatus::Rejected)
        ->and($objTooLong->fbk_failure_reason)->toBe(FeedbackAnalysisService::REASON_TOO_LONG);
    pipelineExpectNoAnalysis($objNoWords);
});

test('failed feedback succeeds on reprocessing, with its issue rows written once', function () {
    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'down']], 500)
        ->push(['error' => ['message' => 'down']], 500)
        ->push(['error' => ['message' => 'down']], 500)
        ->push(pipelineReply('ceb', 'Very crowded and the facilities are poorly maintained.'))]);
    $objFeedback = pipelineFeedback('Daghan kaayo tao ug guba ang mga pasilidad.');

    expect(pipelineProcess($objFeedback)->fbk_status)->toBe(FeedbackAnalysisStatus::Failed);

    $objFeedback = pipelineProcess($objFeedback);

    expect($objFeedback->fbk_status)->toBe(FeedbackAnalysisStatus::Analyzed)
        ->and($objFeedback->fbk_failure_reason)->toBeNull()
        ->and($objFeedback->issues()->pluck('fbi_issue_category')->all())->toBe(['Crowd Management', 'Maintenance']);
});

test('a failure clears issue rows left by an earlier attempt', function () {
    $objFeedback = pipelineFeedback('Daghan kaayo tao', ['fbk_status' => FeedbackAnalysisStatus::Failed]);
    $objFeedback->issues()->create(['fbi_issue_category' => 'Crowd Management', 'fbi_matched_keyword' => 'crowded']);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'down']], 500)]);

    pipelineExpectNoAnalysis(pipelineProcess($objFeedback));
});

test('analyzed and rejected feedback is never processed again', function () {
    Http::fake();
    $objAnalyzed = pipelineFeedback('Nindot kaayo', ['fbk_status' => FeedbackAnalysisStatus::Analyzed, 'fbk_translated_text' => 'Very nice']);
    $objRejected = pipelineFeedback('...', ['fbk_status' => FeedbackAnalysisStatus::Rejected, 'fbk_failure_reason' => 'Feedback has no words to analyze.']);

    expect(pipelineProcess($objAnalyzed)->fbk_translated_text)->toBe('Very nice')
        ->and(pipelineProcess($objRejected)->fbk_status)->toBe(FeedbackAnalysisStatus::Rejected);
    Http::assertNothingSent();
});

test('processing failures are logged without the feedback text', function () {
    Log::spy();
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'down']], 500)]);

    pipelineProcess(pipelineFeedback('Nindot kaayo ang place pero crowded.'));

    Log::shouldHaveReceived('warning')->withArgs(fn (string $strMessage, array $arrContext) => ! str_contains($strMessage.json_encode($arrContext), 'Nindot'));
});

test('the after-response job processes the stored row and ignores a missing one', function () {
    Http::fake();
    $objFeedback = pipelineFeedback('Amazing view and friendly staff');

    ProcessFeedbackAnalysis::dispatchSync($objFeedback->fbk_id);
    ProcessFeedbackAnalysis::dispatchSync(999999);

    expect($objFeedback->fresh()->fbk_status)->toBe(FeedbackAnalysisStatus::Analyzed)
        ->and(Feedback::query()->count())->toBe(1);
});

test('feedback:reprocess handles pending and failed rows only and reports the outcome', function () {
    Http::fake(['api.openai.com/*' => Http::response(pipelineReply('ceb', 'The place is very beautiful but crowded.'))]);
    $objPending = pipelineFeedback('The beach is clean and beautiful');
    $objFailed = pipelineFeedback('Nindot kaayo ang place pero crowded.', ['fbk_status' => FeedbackAnalysisStatus::Failed, 'fbk_failure_reason' => TranslationFailedException::REQUEST_FAILED]);
    $objAnalyzed = pipelineFeedback('Already done', ['fbk_status' => FeedbackAnalysisStatus::Analyzed]);
    $objRejected = pipelineFeedback('...', ['fbk_status' => FeedbackAnalysisStatus::Rejected]);

    $this->artisan('feedback:reprocess')
        ->expectsOutput('Processed 2 feedback: 2 analyzed, 0 failed, 0 rejected.')
        ->assertSuccessful();

    expect($objPending->fresh()->fbk_status)->toBe(FeedbackAnalysisStatus::Analyzed)
        ->and($objFailed->fresh()->fbk_status)->toBe(FeedbackAnalysisStatus::Analyzed)
        ->and($objAnalyzed->fresh()->fbk_translated_text)->toBeNull()
        ->and($objRejected->fresh()->fbk_status)->toBe(FeedbackAnalysisStatus::Rejected);
});

test('feedback:reprocess can be limited to failed rows, and rejects an unknown status', function () {
    Http::fake();
    $objPending = pipelineFeedback('The beach is clean and beautiful');

    $this->artisan('feedback:reprocess', ['--status' => 'failed'])
        ->expectsOutput('Processed 0 feedback: 0 analyzed, 0 failed, 0 rejected.')
        ->assertSuccessful();
    $this->artisan('feedback:reprocess', ['--status' => 'everything'])->assertExitCode(2);

    expect($objPending->fresh()->fbk_status)->toBe(FeedbackAnalysisStatus::Pending);
});

test('the local English check accepts clear English and sends anything uncertain to the provider', function (string $strText, bool $blnExpected) {
    expect(app(EnglishTextDetector::class)->isClearlyEnglish($strText))->toBe($blnExpected);
})->with([
    'fixture 1' => ['The beach is clean and beautiful', true],
    'fixture 2' => ['Amazing view and friendly staff', true],
    'fixture 3' => ['Bad experience and poorly maintained', true],
    'English with negation' => ["The staff wasn't friendly and the room was dirty", true],
    'Cebuano mix' => ['Nindot kaayo ang place pero crowded.', false],
    'Tagalog' => ['Ang ganda ng lugar pero mahal ang food', false],
    'Taglish' => ['The place is beautiful pero mahal', false],
    'Spanish' => ['La playa es muy bonita', false],
    'Korean' => ['해변이 정말 아름다워요', false],
    'uncommon English words only' => ['Superb snorkeling, crystal lagoon', false],
    'no words' => ['!!!', false],
]);
