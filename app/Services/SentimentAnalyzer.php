<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Lexicon-based sentiment analysis of tourist feedback (English, Filipino, Bisaya),
 * combined with the tourist's star rating.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

/**
 * Classifies a piece of feedback as Positive, Neutral or Negative.
 *
 * Algorithm (word lists and tuning values live in config/sentiment.php):
 *  1. Tokenize the lowercased text (split into sentences first).
 *  2. Each word found in the positive/negative lexicon scores +/- its
 *     strength.
 *  3. Context: a negator ("not", "hindi", "dili") within the previous
 *     words flips the score; an intensifier ("very", "kaayo", "napaka")
 *     right before it amplifies it; a diminisher ("medyo") softens it.
 *  4. Contrast: text after "but"/"pero"/"apan" counts more than text
 *     before it, since the writer's conclusion usually follows the "but".
 *  5. The summed score is normalized to -1..1 (sum / sqrt(sum^2 + k)).
 *  6. The star rating (1-5 mapped to -1..1) is blended in. With no
 *     sentiment words in the text, the rating alone decides.
 *  7. |polarity| >= threshold is Positive/Negative, otherwise Neutral.
 */
class SentimentAnalyzer
{
    public const POSITIVE = 'Positive';

    public const NEUTRAL = 'Neutral';

    public const NEGATIVE = 'Negative';

    /**
     * @return array{sentiment: string, polarity: float, language: string, matchedWords: int}
     */
    public function analyze(string $strText, ?int $intRating = null): array
    {
        $arrTuning = config('sentiment.tuning');
        $arrTokens = $this->_tokenize($strText);

        ['score' => $fltTextScore, 'matched' => $intMatched] = $this->_scoreTokens($arrTokens, $arrTuning);

        $fltTextPolarity = $fltTextScore / sqrt(($fltTextScore ** 2) + $arrTuning['normalization']);
        $fltRatingPolarity = $intRating === null ? null : (max(1, min(5, $intRating)) - 3) / 2;

        $fltPolarity = match (true) {
            $fltRatingPolarity === null => $fltTextPolarity,
            $intMatched === 0 => $fltRatingPolarity,
            default => ($arrTuning['text_weight'] * $fltTextPolarity) + ($arrTuning['rating_weight'] * $fltRatingPolarity),
        };
        $fltPolarity = round(max(-1, min(1, $fltPolarity)), 2);

        return [
            'sentiment' => $this->_label($fltPolarity, (float) $arrTuning['threshold']),
            'polarity' => $fltPolarity,
            'language' => $this->detectLanguage($arrTokens),
            'matchedWords' => $intMatched,
        ];
    }

    /**
     * English unless the text carries at least two Filipino or Bisaya
     * marker words; whichever has more markers wins.
     *
     * @param  array<int, string>|string  $mixedTokens
     */
    public function detectLanguage(array|string $mixedTokens): string
    {
        $arrTokens = is_array($mixedTokens) ? $mixedTokens : $this->_tokenize($mixedTokens);
        $arrMarkers = config('sentiment.language_markers');

        $intFilipino = count(array_intersect($arrTokens, $arrMarkers['Filipino']));
        $intBisaya = count(array_intersect($arrTokens, $arrMarkers['Bisaya']));

        if (max($intFilipino, $intBisaya) < 2) {
            return 'English';
        }

        return $intBisaya > $intFilipino ? 'Bisaya' : 'Filipino';
    }

    /**
     * @return array<int, string> Every word, with a "." boundary token after each sentence.
     */
    private function _tokenize(string $strText): array
    {
        $strText = mb_strtolower(str_replace(['’', '‘'], "'", $strText));
        $arrSentences = preg_split('/[.!?;\n]+/u', $strText, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $arrTokens = [];
        foreach ($arrSentences as $strSentence) {
            $arrWords = preg_split("/[^\p{L}'\-]+/u", $strSentence, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($arrWords as $strWord) {
                $arrTokens[] = trim($strWord, "'-");
            }

            $arrTokens[] = '.';
        }

        return array_values(array_filter($arrTokens, fn (string $strToken) => $strToken !== ''));
    }

    /**
     * @param  array<int, string>  $arrTokens
     * @param  array<string, float|int>  $arrTuning
     * @return array{score: float, matched: int}
     */
    private function _scoreTokens(array $arrTokens, array $arrTuning): array
    {
        $arrPositive = config('sentiment.positive');
        $arrNegative = config('sentiment.negative');
        $arrNegators = config('sentiment.negators');
        $arrIntensifiers = config('sentiment.intensifiers');
        $arrDiminishers = config('sentiment.diminishers');
        $arrContrast = config('sentiment.contrast');

        $arrScores = [];
        $intContrastAt = null;
        $intSentenceStart = 0;

        foreach ($arrTokens as $intIndex => $strToken) {
            if ($strToken === '.') {
                $intSentenceStart = $intIndex + 1;

                continue;
            }

            if (in_array($strToken, $arrContrast, true)) {
                $intContrastAt = count($arrScores);

                continue;
            }

            $fltScore = $this->_wordScore($strToken, $arrPositive, $arrNegative, $blnPrefixedIntensifier);

            if ($fltScore === 0.0) {
                continue;
            }

            if ($blnPrefixedIntensifier) {
                $fltScore *= $arrTuning['intensifier_factor'];
            }

            $strPrevious = $intIndex > $intSentenceStart ? $arrTokens[$intIndex - 1] : null;

            if ($strPrevious !== null && in_array($strPrevious, $arrIntensifiers, true)) {
                $fltScore *= $arrTuning['intensifier_factor'];
            } elseif ($strPrevious !== null && in_array($strPrevious, $arrDiminishers, true)) {
                $fltScore *= $arrTuning['diminisher_factor'];
            }

            $intWindowStart = max($intSentenceStart, $intIndex - $arrTuning['negation_window']);
            for ($intLook = $intWindowStart; $intLook < $intIndex; $intLook++) {
                if (in_array($arrTokens[$intLook], $arrNegators, true)) {
                    $fltScore *= $arrTuning['negation_factor'];
                    break;
                }
            }

            $arrScores[] = $fltScore;
        }

        $fltTotal = 0.0;
        foreach ($arrScores as $intPosition => $fltScore) {
            $fltWeight = match (true) {
                $intContrastAt === null => 1.0,
                $intPosition < $intContrastAt => $arrTuning['before_contrast_weight'],
                default => $arrTuning['after_contrast_weight'],
            };
            $fltTotal += $fltScore * $fltWeight;
        }

        return ['score' => $fltTotal, 'matched' => count($arrScores)];
    }

    /**
     * Lexicon lookup. A Filipino "napaka-" / "sobrang" style prefix on a
     * known word ("napakaganda") counts as that word, intensified.
     *
     * @param  array<string, float|int>  $arrPositive
     * @param  array<string, float|int>  $arrNegative
     */
    private function _wordScore(string $strToken, array $arrPositive, array $arrNegative, ?bool &$blnPrefixedIntensifier): float
    {
        $blnPrefixedIntensifier = false;

        foreach ([$strToken, $this->_stripIntensifierPrefix($strToken)] as $intAttempt => $strCandidate) {
            if ($strCandidate === null) {
                continue;
            }

            if (isset($arrPositive[$strCandidate])) {
                $blnPrefixedIntensifier = $intAttempt === 1;

                return (float) $arrPositive[$strCandidate];
            }

            if (isset($arrNegative[$strCandidate])) {
                $blnPrefixedIntensifier = $intAttempt === 1;

                return -1 * (float) $arrNegative[$strCandidate];
            }
        }

        return 0.0;
    }

    private function _stripIntensifierPrefix(string $strToken): ?string
    {
        foreach (['napaka-', 'napaka', 'sobrang-'] as $strPrefix) {
            if (str_starts_with($strToken, $strPrefix) && mb_strlen($strToken) > mb_strlen($strPrefix)) {
                return substr($strToken, strlen($strPrefix));
            }
        }

        return null;
    }

    private function _label(float $fltPolarity, float $fltThreshold): string
    {
        return match (true) {
            $fltPolarity >= $fltThreshold => self::POSITIVE,
            $fltPolarity <= -$fltThreshold => self::NEGATIVE,
            default => self::NEUTRAL,
        };
    }
}
