<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Cheap, local check for feedback that is clearly English, so it
 * is analyzed as written and never sent to the translation provider
 * (Objective 4). Deterministic; no HTTP calls, no AI.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\SentimentLexicon;

/**
 * Conservative on purpose: a false "English" would analyze non-English
 * words as English, so anything uncertain goes to the provider, which
 * detects the language properly. Text is clearly English only when:
 *  - every word uses plain Latin letters, digits, or apostrophes;
 *  - no word is a common Cebuano/Tagalog marker ("ang", "kaayo", "pero");
 *  - at least config('tourist_feedback.translation.english_word_ratio') of
 *    its words are common English words or sentiment lexicon words.
 */
class EnglishTextDetector
{
    /**
     * Common English function words and everyday tourism words.
     *
     * @var array<int, string>
     */
    private const ENGLISH_WORDS = [
        'a', 'about', 'after', 'again', 'all', 'also', 'am', 'an', 'and', 'any', 'are', 'around', 'as', 'at',
        'be', 'because', 'been', 'before', 'being', 'but', 'by', 'can', 'could', 'did', 'do', 'does', 'during',
        'each', 'even', 'every', 'for', 'from', 'get', 'got', 'had', 'has', 'have', 'he', 'her', 'here', 'him',
        'his', 'how', 'i', "i'm", 'if', 'in', 'into', 'is', 'it', "it's", 'its', 'just', 'like', 'little', 'lot',
        'many', 'me', 'more', 'most', 'much', 'my', 'no', 'not', 'of', 'on', 'only', 'or', 'other', 'our', 'out',
        'over', 'really', 'she', 'so', 'some', 'than', 'that', 'the', 'their', 'them', 'then', 'there', 'they',
        'this', 'those', 'though', 'to', 'too', 'up', 'us', 'very', 'was', 'way', 'we', 'well', 'were', 'what',
        'when', 'where', 'which', 'while', 'who', 'will', 'with', 'would', 'you', 'your',
        "isn't", "wasn't", "don't", "doesn't", "didn't", "can't", 'cannot', 'never', 'hardly',
        'area', 'arrived', 'beach', 'boat', 'came', 'day', 'enjoyed', 'entrance', 'everyone', 'everything',
        'experience', 'family', 'fee', 'food', 'go', 'guide', 'hotel', 'island', 'kids', 'loved', 'maintained',
        'people', 'place', 'price', 'recommend', 'resort', 'restaurant', 'room', 'rooms', 'sea', 'service',
        'staff', 'stay', 'stayed', 'time', 'tour', 'trip', 'view', 'visit', 'visited', 'water', 'went',
    ];

    /**
     * Frequent Cebuano and Tagalog words; one is enough to send the text
     * to the provider (Taglish and Bisaya-English mixes).
     *
     * @var array<int, string>
     */
    private const PHILIPPINE_MARKERS = [
        'ang', 'ng', 'mga', 'sa', 'na', 'ni', 'si', 'ug', 'nga', 'kay', 'pero', 'kaayo', 'nindot', 'maayo',
        'lami', 'gyud', 'jud', 'pud', 'diri', 'dili', 'wala', 'kami', 'namo', 'nako', 'ganda', 'maganda',
        'masarap', 'mahal', 'lugar', 'dito', 'hindi', 'naman', 'talaga', 'sobrang', 'medyo', 'grabe', 'po',
        'lang', 'din', 'rin', 'salamat', 'ayos', 'mabaho', 'baho', 'dagat', 'tubig', 'kayo', 'ba', 'ko', 'mo',
    ];

    public function __construct(private readonly FeedbackTextTokenizer $objTokenizer) {}

    /**
     * Whether the feedback can skip translation and be analyzed as written.
     */
    public function isClearlyEnglish(string $strText): bool
    {
        $arrTokens = $this->objTokenizer->tokenize($strText);

        if ($arrTokens === []) {
            return false;
        }

        $arrLexiconWords = SentimentLexicon::getCachedPolarities();
        $intEnglishCount = 0;

        // Summary comment: any non-Latin word or Philippine marker ends the
        // check at once; otherwise count the recognized English words.
        foreach ($arrTokens as $strToken) {
            $blnIsPlainLatin = preg_match("/^[a-z0-9']+$/", $strToken) === 1;
            $blnIsMarker = in_array($strToken, self::PHILIPPINE_MARKERS, true);

            if (! $blnIsPlainLatin || $blnIsMarker) {
                return false;
            }

            $blnIsEnglishWord = in_array($strToken, self::ENGLISH_WORDS, true) || array_key_exists($strToken, $arrLexiconWords);

            if ($blnIsEnglishWord) {
                $intEnglishCount++;
            }
        } // end foreach token

        return $intEnglishCount / count($arrTokens) >= (float) config('tourist_feedback.translation.english_word_ratio');
    }
}
