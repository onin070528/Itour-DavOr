<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — lexicon-based sentiment analyzer (no database needed).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Services\SentimentAnalyzer;

test('clearly positive and negative comments are classified', function (string $strText, int $intRating, string $strExpected) {
    expect((new SentimentAnalyzer)->analyze($strText, $intRating)['sentiment'])->toBe($strExpected);
})->with([
    'positive English' => ['The falls are amazing and the staff were friendly.', 5, 'Positive'],
    'negative English' => ['Dirty rooms and the staff were rude.', 1, 'Negative'],
    'positive Filipino' => ['Napakaganda ng dagat, malinis at tahimik.', 5, 'Positive'],
    'negative Filipino' => ['Pangit ang serbisyo at madumi ang cr.', 1, 'Negative'],
    'positive Bisaya' => ['Lami kaayo ang kinilaw. Balikan gyud.', 5, 'Positive'],
    'negative Bisaya' => ['Dili nindot ang pagsilbi, hugaw kaayo.', 2, 'Negative'],
]);

test('negation flips a positive word', function () {
    $arrResult = (new SentimentAnalyzer)->analyze('The food was not delicious and the staff were not friendly.', null);

    expect($arrResult['sentiment'])->toBe('Negative');
});

test('text after "but" outweighs the text before it', function () {
    $arrResult = (new SentimentAnalyzer)->analyze('The falls are beautiful but the road is very difficult with many potholes.', null);

    expect($arrResult['sentiment'])->toBe('Negative');
});

test('a comment with no sentiment words falls back to the star rating', function () {
    $objAnalyzer = new SentimentAnalyzer;

    expect($objAnalyzer->analyze('We stayed one night.', 3)['sentiment'])->toBe('Neutral')
        ->and($objAnalyzer->analyze('We stayed one night.', 5)['sentiment'])->toBe('Positive')
        ->and($objAnalyzer->analyze('We stayed one night.', 1)['sentiment'])->toBe('Negative');
});

test('the star rating pulls a mixed comment toward the rating', function () {
    $objAnalyzer = new SentimentAnalyzer;

    expect($objAnalyzer->analyze('Waves were decent but the camp ran out of boards.', 3)['sentiment'])->toBe('Neutral');
});

test('language is detected from marker words', function () {
    $objAnalyzer = new SentimentAnalyzer;

    expect($objAnalyzer->analyze('Napakaganda ng dagat, malinis ang paligid dito sa Dahican.', 5)['language'])->toBe('Filipino')
        ->and($objAnalyzer->analyze('Lami kaayo ang kinilaw ug nindot gyud ang dagat.', 5)['language'])->toBe('Bisaya')
        ->and($objAnalyzer->analyze('Great place for a quiet weekend.', 5)['language'])->toBe('English');
});

test('polarity stays within -1 and 1', function () {
    $arrResult = (new SentimentAnalyzer)->analyze(str_repeat('amazing wonderful perfect best ', 30), 5);

    expect($arrResult['polarity'])->toBeLessThanOrEqual(1.0)->toBeGreaterThanOrEqual(-1.0);
});
