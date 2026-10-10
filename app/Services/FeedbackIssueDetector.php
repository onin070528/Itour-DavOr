<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Deterministic recurring-issue detection for negative tourist
 * feedback (Objective 4). Matches the English text against the keyword and
 * phrase list in tbl_feedback_issue_lexicons. No HTTP calls, no AI.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Enums\SentimentClassification;
use App\Models\FeedbackIssueLexicon;
use App\Support\FeedbackTextTokenizer;

class FeedbackIssueDetector
{
    /**
     * Issue phrases as token lists, longest first; built once per instance.
     *
     * @var array<int, array{tokens: array<int, string>, keyword: string, category: string}>|null
     */
    private ?array $arrPhrases = null;

    public function __construct(private readonly FeedbackTextTokenizer $objTokenizer) {}

    /**
     * Detects the issue categories in one review.
     *
     * Rules:
     *  - Only Negative feedback is checked; any other class returns [].
     *  - Keywords may be phrases ("poorly maintained", "too many people").
     *    At each position the LONGEST matching phrase wins and its words
     *    are consumed, so "plastic waste" is Environment, not also
     *    Cleanliness through "waste".
     *  - A match right after a negator ("not dirty") is ignored.
     *  - Each category is reported once, with the first keyword that
     *    matched it, in order of appearance.
     *  - Negative feedback with no known issue gets the 'Other' category
     *    (no keyword).
     *
     * @return array<int, array{category: string, keyword: string|null}>
     */
    public function detect(string $strEnglishText, ?SentimentClassification $objClassification): array
    {
        if ($objClassification !== SentimentClassification::Negative) {
            return [];
        }

        $arrTokens = $this->objTokenizer->tokenize($strEnglishText);
        $intTokenCount = count($arrTokens);
        $arrIssues = [];
        $intPosition = 0;

        // Summary comment: scan left to right; on a phrase match, record its
        // category unless negated, then skip past the matched words.
        while ($intPosition < $intTokenCount) {
            $arrMatch = $this->_matchAt($arrTokens, $intPosition);

            if ($arrMatch === null) {
                $intPosition++;

                continue;
            }

            $blnIsNegated = $intPosition > 0 && $this->objTokenizer->isNegator($arrTokens[$intPosition - 1]);
            $blnIsNewCategory = ! array_key_exists($arrMatch['category'], $arrIssues);

            if (! $blnIsNegated && $blnIsNewCategory) {
                $arrIssues[$arrMatch['category']] = $arrMatch['keyword'];
            }

            $intPosition += count($arrMatch['tokens']);
        } // end while scanning tokens

        if ($arrIssues === []) {
            return [['category' => config('tourist_feedback.other_issue_category'), 'keyword' => null]];
        }

        $arrResult = [];

        foreach ($arrIssues as $strCategory => $strKeyword) {
            $arrResult[] = ['category' => $strCategory, 'keyword' => $strKeyword];
        }

        return $arrResult;
    }

    /**
     * The longest issue phrase that starts at this token position, if any.
     *
     * @param  array<int, string>  $arrTokens
     * @return array{tokens: array<int, string>, keyword: string, category: string}|null
     */
    private function _matchAt(array $arrTokens, int $intPosition): ?array
    {
        foreach ($this->_phrases() as $arrPhrase) {
            $intLength = count($arrPhrase['tokens']);
            $arrWindow = array_slice($arrTokens, $intPosition, $intLength);

            if ($arrWindow === $arrPhrase['tokens']) {
                return $arrPhrase;
            }
        }

        return null;
    }

    /**
     * The cached issue lexicon, tokenized with the same tokenizer as the
     * feedback text and sorted longest phrase first.
     *
     * @return array<int, array{tokens: array<int, string>, keyword: string, category: string}>
     */
    private function _phrases(): array
    {
        if ($this->arrPhrases !== null) {
            return $this->arrPhrases;
        }

        $arrPhrases = [];

        foreach (FeedbackIssueLexicon::getCachedKeywords() as $strKeyword => $strCategory) {
            $arrKeywordTokens = $this->objTokenizer->tokenize((string) $strKeyword);

            if ($arrKeywordTokens !== []) {
                $arrPhrases[] = ['tokens' => $arrKeywordTokens, 'keyword' => (string) $strKeyword, 'category' => $strCategory];
            }
        }

        usort($arrPhrases, fn (array $arrFirst, array $arrSecond) => count($arrSecond['tokens']) <=> count($arrFirst['tokens']));

        $this->arrPhrases = $arrPhrases;

        return $this->arrPhrases;
    }
}
