<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Predefined, deterministic recommendations for tourist feedback
 * analytics (Objective 4): the category -> recommendation lookup, the
 * "negative feedback dominates" rule, and the Common Concerns / Suggested
 * Improvements summary for one listing. No HTTP calls, no generative AI;
 * the only recommendation text is config('tourist_feedback.issue_categories').
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

class FeedbackRecommendationService
{
    /**
     * The predefined recommendation for an issue category; null for
     * 'Other' and for any unknown category.
     */
    public function recommendationFor(string $strCategory): ?string
    {
        return config('tourist_feedback.issue_categories')[$strCategory] ?? null;
    }

    /**
     * Negative feedback dominates a listing when its negative count is
     * greater than BOTH its positive and its neutral counts, AND it has at
     * least config('tourist_feedback.minimum_sample') analyzed reviews.
     */
    public function isNegativeDominant(int $intPositiveCount, int $intNeutralCount, int $intNegativeCount): bool
    {
        $intTotal = $intPositiveCount + $intNeutralCount + $intNegativeCount;
        $blnHasMinimumSample = $intTotal >= (int) config('tourist_feedback.minimum_sample');
        $blnIsMostlyNegative = $intNegativeCount > $intPositiveCount && $intNegativeCount > $intNeutralCount;

        return $blnHasMinimumSample && $blnIsMostlyNegative;
    }

    /**
     * Builds the analytics summary for one listing from its analyzed
     * sentiment counts and its issue counts (category => number of
     * feedback rows that mention it).
     *
     *  - Common Concerns: every category with a count, most mentioned
     *    first; ties keep the configured category order.
     *  - Suggested Improvements: the recommendations for those concerns
     *    ('Other' has none), only when negative feedback dominates;
     *    otherwise empty (shown as "None").
     *
     * @param  array<string, int>  $arrIssueCounts
     * @return array{
     *     is_negative_dominant: bool,
     *     common_concerns: array<int, array{category: string, count: int}>,
     *     suggested_improvements: array<int, string>
     * }
     */
    public function summarize(int $intPositiveCount, int $intNeutralCount, int $intNegativeCount, array $arrIssueCounts): array
    {
        $blnIsNegativeDominant = $this->isNegativeDominant($intPositiveCount, $intNeutralCount, $intNegativeCount);
        $arrConcerns = $this->_rankConcerns($arrIssueCounts);
        $arrImprovements = [];

        if ($blnIsNegativeDominant) {
            foreach ($arrConcerns as $arrConcern) {
                $strRecommendation = $this->recommendationFor($arrConcern['category']);

                if ($strRecommendation !== null) {
                    $arrImprovements[] = $strRecommendation;
                }
            }
        }

        return [
            'is_negative_dominant' => $blnIsNegativeDominant,
            'common_concerns' => $arrConcerns,
            'suggested_improvements' => $arrImprovements,
        ];
    }

    /**
     * Common Concerns for any scope (a municipality, the province): the same
     * ranking summarize() uses, without the per-listing improvement rule.
     *
     * @param  array<string, int>  $arrIssueCounts
     * @return array<int, array{category: string, count: int}>
     */
    public function rankConcerns(array $arrIssueCounts): array
    {
        return $this->_rankConcerns($arrIssueCounts);
    }

    /**
     * Categories with a positive count, most mentioned first; ties follow
     * the configured category order (unknown categories last).
     *
     * @param  array<string, int>  $arrIssueCounts
     * @return array<int, array{category: string, count: int}>
     */
    private function _rankConcerns(array $arrIssueCounts): array
    {
        $arrCategoryOrder = array_flip(array_keys(config('tourist_feedback.issue_categories')));
        $arrConcerns = [];

        foreach ($arrIssueCounts as $strCategory => $intCount) {
            if ($intCount > 0) {
                $arrConcerns[] = ['category' => (string) $strCategory, 'count' => (int) $intCount];
            }
        }

        usort($arrConcerns, function (array $arrFirst, array $arrSecond) use ($arrCategoryOrder) {
            $intByCount = $arrSecond['count'] <=> $arrFirst['count'];
            $intFirstOrder = $arrCategoryOrder[$arrFirst['category']] ?? PHP_INT_MAX;
            $intSecondOrder = $arrCategoryOrder[$arrSecond['category']] ?? PHP_INT_MAX;

            return $intByCount !== 0 ? $intByCount : $intFirstOrder <=> $intSecondOrder;
        });

        return $arrConcerns;
    }
}
