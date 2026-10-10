<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The tourist feedback processing pipeline (Objective 4): validate
 * the stored feedback, detect its language, translate it into English when
 * needed, score it with the lexicon, detect issues in negative feedback,
 * and store the result with its processing status.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Enums\FeedbackAnalysisStatus;
use App\Exceptions\TranslationFailedException;
use App\Models\Feedback;
use App\Support\EnglishTextDetector;
use App\Support\FeedbackTextTokenizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pipeline (Objective 4, R5):
 *
 *   pending -> validate -> detect language
 *     -> English? use the original : translate (TranslationService)
 *     -> lexicon sentiment on the English text (SentimentAnalysisService)
 *     -> issues, Negative only (FeedbackIssueDetector)
 *     -> store -> analyzed
 *
 * Outcomes:
 *  - analyzed: translation (or the English original) and every analysis
 *    field stored; issue rows replaced.
 *  - failed: the language could not be detected or the text could not be
 *    translated. No translation, score, class, or issue is stored — the
 *    original non-English text is never analyzed as if it were English.
 *    Failed rows can be processed again (feedback:reprocess).
 *  - rejected: nothing to analyze (no words, T = 0) or the text exceeds
 *    the configured maximum length.
 * fbk_original_text is never changed. Only pending and failed rows are
 * processed; analyzed and rejected rows are left as they are.
 */
class FeedbackAnalysisService
{
    public const REASON_NO_WORDS = 'Feedback has no words to analyze.';

    public const REASON_TOO_LONG = 'Feedback exceeds the maximum length.';

    public const REASON_UNEXPECTED = 'Unexpected processing error.';

    public function __construct(
        private readonly TranslationService $objTranslator,
        private readonly EnglishTextDetector $objEnglishDetector,
        private readonly FeedbackTextTokenizer $objTokenizer,
        private readonly SentimentAnalysisService $objSentiment,
        private readonly FeedbackIssueDetector $objIssueDetector,
    ) {}

    /**
     * Whether a row with this status may be (re)processed.
     */
    public function isProcessable(Feedback $objFeedback): bool
    {
        return in_array($objFeedback->fbk_status, [FeedbackAnalysisStatus::Pending, FeedbackAnalysisStatus::Failed], true);
    }

    /**
     * Runs the pipeline for one feedback row and returns it refreshed.
     */
    public function process(Feedback $objFeedback): Feedback
    {
        if (! $this->isProcessable($objFeedback)) {
            return $objFeedback;
        }

        $strOriginal = (string) $objFeedback->fbk_original_text;

        // Step 1: validate what was stored.
        if (mb_strlen($strOriginal) > (int) config('tourist_feedback.feedback_max_length')) {
            return $this->_reject($objFeedback, self::REASON_TOO_LONG);
        }

        if ($this->objTokenizer->tokenize($strOriginal) === []) {
            return $this->_reject($objFeedback, self::REASON_NO_WORDS);
        }

        // Steps 2-3: language and English text.
        try {
            [$strLanguage, $strEnglishText] = $this->_resolveEnglishText($strOriginal);
        } catch (TranslationFailedException $objException) {
            return $this->_fail($objFeedback, $objException->getMessage());
        } catch (Throwable $objException) {
            Log::error('Tourist feedback processing failed unexpectedly.', ['feedback_id' => $objFeedback->fbk_id, 'exception' => class_basename($objException)]);

            return $this->_fail($objFeedback, self::REASON_UNEXPECTED);
        }

        // Steps 4-5: deterministic analysis of the English text only.
        $arrSentiment = $this->objSentiment->analyze($strEnglishText);

        if ($arrSentiment['total_word_count'] === 0) {
            return $this->_reject($objFeedback, self::REASON_NO_WORDS);
        }

        $arrIssues = $this->objIssueDetector->detect($strEnglishText, $arrSentiment['sentiment_classification']);

        // Step 6: store everything at once.
        return $this->_storeAnalysis($objFeedback, $strLanguage, $strEnglishText, $arrSentiment, $arrIssues);
    }

    /**
     * Clearly English text is used as written (no provider call). Anything
     * else goes to the provider: if it reports English, the original is
     * still used unchanged; otherwise its translation is used.
     *
     * @return array{0: string, 1: string} [language code, English text]
     */
    private function _resolveEnglishText(string $strOriginal): array
    {
        if ($this->objEnglishDetector->isClearlyEnglish($strOriginal)) {
            return ['en', $strOriginal];
        }

        $strLanguage = $this->objTranslator->detectLanguage($strOriginal);

        if ($strLanguage === 'en') {
            return ['en', $strOriginal];
        }

        return [$strLanguage, $this->objTranslator->translateToEnglish($strOriginal)['translated_text']];
    }

    /**
     * Stores the analyzed result and replaces the feedback's issue rows in
     * one transaction.
     *
     * @param  array<string, mixed>  $arrSentiment
     * @param  array<int, array{category: string, keyword: string|null}>  $arrIssues
     */
    private function _storeAnalysis(Feedback $objFeedback, string $strLanguage, string $strEnglishText, array $arrSentiment, array $arrIssues): Feedback
    {
        DB::transaction(function () use ($objFeedback, $strLanguage, $strEnglishText, $arrSentiment, $arrIssues) {
            $objFeedback->forceFill([
                'fbk_status' => FeedbackAnalysisStatus::Analyzed,
                'fbk_detected_language' => $strLanguage,
                'fbk_translated_text' => $strEnglishText,
                'fbk_positive_count' => $arrSentiment['positive_word_count'],
                'fbk_negative_count' => $arrSentiment['negative_word_count'],
                'fbk_total_word_count' => $arrSentiment['total_word_count'],
                'fbk_sentiment_score' => $arrSentiment['sentiment_score'],
                'fbk_sentiment' => $arrSentiment['sentiment_classification'],
                'fbk_matched_terms' => $arrSentiment['matched_terms'],
                'fbk_failure_reason' => null,
                'fbk_analyzed_at' => now(),
            ])->save();

            $objFeedback->issues()->delete();

            foreach ($arrIssues as $arrIssue) {
                $objFeedback->issues()->create([
                    'fbi_issue_category' => $arrIssue['category'],
                    'fbi_matched_keyword' => $arrIssue['keyword'],
                ]);
            }
        });

        return $objFeedback->refresh();
    }

    private function _fail(Feedback $objFeedback, string $strReason): Feedback
    {
        return $this->_storeOutcome($objFeedback, FeedbackAnalysisStatus::Failed, $strReason);
    }

    private function _reject(Feedback $objFeedback, string $strReason): Feedback
    {
        return $this->_storeOutcome($objFeedback, FeedbackAnalysisStatus::Rejected, $strReason);
    }

    /**
     * Failed or rejected: the reason is stored and every analysis field is
     * cleared, so no partial or fabricated result can be counted.
     */
    private function _storeOutcome(Feedback $objFeedback, FeedbackAnalysisStatus $objStatus, string $strReason): Feedback
    {
        DB::transaction(function () use ($objFeedback, $objStatus, $strReason) {
            $objFeedback->forceFill([
                'fbk_status' => $objStatus,
                'fbk_failure_reason' => $strReason,
                'fbk_detected_language' => null,
                'fbk_translated_text' => null,
                'fbk_positive_count' => null,
                'fbk_negative_count' => null,
                'fbk_total_word_count' => null,
                'fbk_sentiment_score' => null,
                'fbk_sentiment' => null,
                'fbk_matched_terms' => null,
                'fbk_analyzed_at' => null,
            ])->save();

            $objFeedback->issues()->delete();
        });

        return $objFeedback->refresh();
    }
}
