<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Lexicon-Based Sentiment Analysis with Polarity Scoring
 * (Objective 4). Scores English (original or translated) tourist feedback
 * deterministically against tbl_sentiment_lexicons. No HTTP calls, no AI,
 * no machine learning: the same text and lexicon always give the same
 * result, and every result can be explained from its matched words.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Enums\SentimentClassification;
use App\Models\SentimentLexicon;
use App\Support\FeedbackTextTokenizer;

class SentimentAnalysisService
{
    public function __construct(private readonly FeedbackTextTokenizer $objTokenizer) {}

    /**
     * Scores one English review with the documented formula:
     *
     *     S = (P - N) / T
     *
     *     P = positive lexicon words found
     *     N = negative lexicon words found
     *     T = total words in the processed review (counted from the actual
     *         text, never a fixed value)
     *
     *     S > 0 -> Positive, S = 0 -> Neutral, S < 0 -> Negative
     *
     * Negation rule (window of one token): when the word right before a
     * lexicon word is a negator (FeedbackTextTokenizer::NEGATORS), that
     * word counts with the opposite polarity — "not good" adds 1 to N,
     * "not crowded" adds 1 to P. Only the immediately preceding token is
     * checked, so "not very good" still counts "good" as positive.
     *
     * When T = 0 (nothing left after normalization) there is no division:
     * the score and classification are null, and the caller marks the
     * feedback rejected. The score is rounded to 4 decimals; the
     * classification uses the exact sign of P - N.
     *
     * @return array{
     *     positive_word_count: int,
     *     negative_word_count: int,
     *     total_word_count: int,
     *     sentiment_score: float|null,
     *     sentiment_classification: SentimentClassification|null,
     *     matched_terms: array{positive: array<int, string>, negative: array<int, string>}
     * }
     */
    public function analyze(string $strEnglishText): array
    {
        $arrTokens = $this->objTokenizer->tokenize($strEnglishText);
        $intTotalWords = count($arrTokens);
        $arrPolarities = SentimentLexicon::getCachedPolarities();
        $arrPositiveTerms = [];
        $arrNegativeTerms = [];

        // Summary comment: walk the tokens once, look each one up in the
        // lexicon, and flip its polarity when the previous token negates it.
        foreach ($arrTokens as $intIndex => $strToken) {
            $strPolarity = $arrPolarities[$strToken] ?? null;

            if ($strPolarity === null) {
                continue;
            }

            $blnIsNegated = $intIndex > 0 && $this->objTokenizer->isNegator($arrTokens[$intIndex - 1]);
            $blnCountsAsPositive = ($strPolarity === SentimentLexicon::POLARITY_POSITIVE) !== $blnIsNegated;
            $strMatchedTerm = $blnIsNegated ? $arrTokens[$intIndex - 1].' '.$strToken : $strToken;

            if ($blnCountsAsPositive) {
                $arrPositiveTerms[] = $strMatchedTerm;
            } else {
                $arrNegativeTerms[] = $strMatchedTerm;
            }
        } // end foreach token

        $intPositiveCount = count($arrPositiveTerms);
        $intNegativeCount = count($arrNegativeTerms);

        return [
            'positive_word_count' => $intPositiveCount,
            'negative_word_count' => $intNegativeCount,
            'total_word_count' => $intTotalWords,
            'sentiment_score' => $this->_score($intPositiveCount, $intNegativeCount, $intTotalWords),
            'sentiment_classification' => $this->_classify($intPositiveCount, $intNegativeCount, $intTotalWords),
            'matched_terms' => ['positive' => $arrPositiveTerms, 'negative' => $arrNegativeTerms],
        ];
    }

    /**
     * S = (P - N) / T, rounded to 4 decimals; null when T = 0.
     */
    private function _score(int $intPositiveCount, int $intNegativeCount, int $intTotalWords): ?float
    {
        if ($intTotalWords === 0) {
            return null;
        }

        return round(($intPositiveCount - $intNegativeCount) / $intTotalWords, 4);
    }

    /**
     * The class follows the sign of S, which (T > 0) is the sign of P - N.
     */
    private function _classify(int $intPositiveCount, int $intNegativeCount, int $intTotalWords): ?SentimentClassification
    {
        if ($intTotalWords === 0) {
            return null;
        }

        $intDifference = $intPositiveCount - $intNegativeCount;

        return match (true) {
            $intDifference > 0 => SentimentClassification::Positive,
            $intDifference < 0 => SentimentClassification::Negative,
            default => SentimentClassification::Neutral,
        };
    }
}
