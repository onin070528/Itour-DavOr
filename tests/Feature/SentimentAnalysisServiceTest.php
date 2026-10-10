<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — Objective 4 Phase 2: the lexicon-based polarity scoring
 * (S = (P - N) / T), the documented example fixtures, tokenization,
 * negation, empty or invalid text, and proof that the analysis core makes
 * no HTTP or AI calls.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\SentimentClassification;
use App\Models\SentimentLexicon;
use App\Services\FeedbackIssueDetector;
use App\Services\FeedbackRecommendationService;
use App\Services\SentimentAnalysisService;
use App\Support\EnglishTextDetector;
use App\Support\FeedbackTextTokenizer;
use Database\Seeders\FeedbackIssueLexiconSeeder;
use Database\Seeders\SentimentLexiconSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(SentimentLexiconSeeder::class);
    $this->seed(FeedbackIssueLexiconSeeder::class);
});

function sentimentOf(string $strText): array
{
    return app(SentimentAnalysisService::class)->analyze($strText);
}

/*
 * The documented examples (Objective 4 fixtures table). T is counted from
 * the English text the app actually analyzes — for the two translated
 * reviews that is the translation, so T differs from the manuscript's
 * Table 7, which counted the original-language words.
 */
dataset('documented examples', [
    'clean and beautiful beach' => ['The beach is clean and beautiful', 2, 0, 6, 0.3333, SentimentClassification::Positive],
    'amazing view, friendly staff' => ['Amazing view and friendly staff', 2, 0, 5, 0.4, SentimentClassification::Positive],
    'bad and poorly maintained' => ['Bad experience and poorly maintained', 0, 2, 5, -0.4, SentimentClassification::Negative],
    'beautiful but crowded (Cebuano translation)' => ['The place is very beautiful but crowded', 1, 1, 7, 0.0, SentimentClassification::Neutral],
    'beautiful but expensive (Tagalog translation)' => ['The place is beautiful but the food is expensive', 1, 1, 9, 0.0, SentimentClassification::Neutral],
]);

test('the documented examples give the exact P, N, T, S, and class', function (string $strText, int $intPositive, int $intNegative, int $intTotal, float $fltScore, SentimentClassification $objClass) {
    $arrResult = sentimentOf($strText);

    expect($arrResult['positive_word_count'])->toBe($intPositive)
        ->and($arrResult['negative_word_count'])->toBe($intNegative)
        ->and($arrResult['total_word_count'])->toBe($intTotal)
        ->and($arrResult['sentiment_score'])->toBe($fltScore)
        ->and($arrResult['sentiment_classification'])->toBe($objClass);
})->with('documented examples');

test('the score follows S = (P - N) / T, rounded to 4 decimals, and the matched words explain it', function () {
    $arrResult = sentimentOf('The beach is clean and beautiful');

    expect($arrResult['sentiment_score'])->toBe(round((2 - 0) / 6, 4))
        ->and(number_format($arrResult['sentiment_score'], 2))->toBe('0.33')
        ->and($arrResult['matched_terms'])->toBe(['positive' => ['clean', 'beautiful'], 'negative' => []]);
});

test('S > 0 is Positive, S = 0 is Neutral, S < 0 is Negative', function () {
    expect(sentimentOf('Great and friendly')['sentiment_classification'])->toBe(SentimentClassification::Positive)
        ->and(sentimentOf('We arrived on Sunday morning')['sentiment_classification'])->toBe(SentimentClassification::Neutral)
        ->and(sentimentOf('We arrived on Sunday morning')['sentiment_score'])->toBe(0.0)
        ->and(sentimentOf('Good food but dirty and noisy')['sentiment_classification'])->toBe(SentimentClassification::Negative);
});

test('every occurrence of a lexicon word is counted', function () {
    $arrResult = sentimentOf('Clean, clean, clean!');

    expect($arrResult['positive_word_count'])->toBe(3)
        ->and($arrResult['total_word_count'])->toBe(3)
        ->and($arrResult['sentiment_score'])->toBe(1.0);
});

test('case and punctuation do not change the result', function () {
    $arrPlain = sentimentOf('The beach is clean and beautiful');
    $arrNoisy = sentimentOf('THE beach... is CLEAN, and Beautiful!!! 🌊');

    expect($arrNoisy)->toBe($arrPlain);
});

test('apostrophes stay inside a word, typographic apostrophes are normalized, and hyphens split words', function () {
    $objTokenizer = new FeedbackTextTokenizer;

    expect($objTokenizer->tokenize("It isn't the beach's fault"))->toBe(['it', "isn't", 'the', "beach's", 'fault'])
        ->and($objTokenizer->tokenize('It isn’t clean'))->toBe(['it', "isn't", 'clean'])
        ->and($objTokenizer->tokenize('A well-kept, 2-hour trail'))->toBe(['a', 'well', 'kept', '2', 'hour', 'trail']);
});

test('a negator flips the next lexicon word: "not good" is negative, "not crowded" is positive', function () {
    $arrNotGood = sentimentOf('The food was not good');
    $arrNotCrowded = sentimentOf('The beach was not crowded');

    expect($arrNotGood['negative_word_count'])->toBe(1)
        ->and($arrNotGood['positive_word_count'])->toBe(0)
        ->and($arrNotGood['matched_terms']['negative'])->toBe(['not good'])
        ->and($arrNotGood['sentiment_classification'])->toBe(SentimentClassification::Negative)
        ->and($arrNotCrowded['positive_word_count'])->toBe(1)
        ->and($arrNotCrowded['matched_terms']['positive'])->toBe(['not crowded'])
        ->and($arrNotCrowded['sentiment_classification'])->toBe(SentimentClassification::Positive);
});

test('every listed negator flips, including contractions with a typographic apostrophe', function (string $strNegator) {
    $arrResult = sentimentOf("The staff {$strNegator} friendly");

    expect($arrResult['negative_word_count'])->toBe(1)
        ->and($arrResult['positive_word_count'])->toBe(0);
})->with(['not', 'never', "isn't", "wasn't", "don't", "doesn't", "didn't", "can't", 'cannot', 'hardly', 'wasn’t']);

test('negation looks back one token only and negators count toward T', function () {
    // "very" sits between the negator and "good", so "good" is not flipped.
    $arrResult = sentimentOf('Not very good');

    expect($arrResult['positive_word_count'])->toBe(1)
        ->and($arrResult['negative_word_count'])->toBe(0)
        ->and($arrResult['total_word_count'])->toBe(3)
        ->and(sentimentOf('not good')['sentiment_score'])->toBe(-0.5)
        ->and(sentimentOf('The trip was good, not bad')['matched_terms'])->toBe(['positive' => ['good', 'not bad'], 'negative' => []]);
});

test('empty or unusable text has T = 0 and is never divided or classified', function (string $strText) {
    $arrResult = sentimentOf($strText);

    expect($arrResult['total_word_count'])->toBe(0)
        ->and($arrResult['positive_word_count'])->toBe(0)
        ->and($arrResult['negative_word_count'])->toBe(0)
        ->and($arrResult['sentiment_score'])->toBeNull()
        ->and($arrResult['sentiment_classification'])->toBeNull();
})->with([
    'empty' => [''],
    'whitespace' => ["   \n\t  "],
    'punctuation only' => ['!!! ... ???'],
    'emoji only' => ['😀😀👍'],
    'invalid UTF-8' => ["\xC3\x28\xA0\xA1"],
]);

test('lexicon edits take effect on the next analysis', function () {
    expect(sentimentOf('A majestic view')['positive_word_count'])->toBe(0);

    SentimentLexicon::query()->create(['slx_word' => 'majestic', 'slx_polarity' => SentimentLexicon::POLARITY_POSITIVE]);

    expect(sentimentOf('A majestic view')['positive_word_count'])->toBe(1);
});

test('the analysis core never makes an HTTP request', function () {
    Http::preventStrayRequests();
    Http::fake();

    $objClass = sentimentOf('Very crowded and the facilities are poorly maintained')['sentiment_classification'];
    app(FeedbackIssueDetector::class)->detect('Very crowded and the facilities are poorly maintained', $objClass);
    app(FeedbackRecommendationService::class)->summarize(1, 1, 3, ['Crowd Management' => 2]);

    Http::assertNothingSent();
});

test('the analysis core source has no HTTP, network, process, or AI dependency', function (string $strClass) {
    $strSource = file_get_contents((new ReflectionClass($strClass))->getFileName());
    $arrForbidden = [
        'Illuminate\Support\Facades\Http', 'Http::', 'GuzzleHttp', 'curl_', 'file_get_contents', 'fsockopen',
        'stream_socket', 'Symfony\Component\Process', 'Process::', 'openai', 'OpenAI', 'TranslationService',
    ];

    foreach ($arrForbidden as $strNeedle) {
        expect(str_contains($strSource, $strNeedle))->toBeFalse("{$strClass} must not reference {$strNeedle}");
    }
})->with([
    SentimentAnalysisService::class,
    FeedbackIssueDetector::class,
    FeedbackRecommendationService::class,
    FeedbackTextTokenizer::class,
    EnglishTextDetector::class,
]);
