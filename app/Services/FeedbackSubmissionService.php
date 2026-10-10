<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Stores one validated public tourist feedback submission
 * (Objective 4): duplicate protection, the honeypot outcome, consent
 * time, and handing the row to the analysis job after the response.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Enums\FeedbackAnalysisStatus;
use App\Jobs\ProcessFeedbackAnalysis;
use App\Models\Feedback;
use App\Models\Listing;
use App\Support\FeedbackTextTokenizer;
use Illuminate\Support\Facades\Cache;

/**
 * Every outcome looks the same to the tourist (the thank-you page), so a
 * bot or a repeated submission learns nothing:
 *  - stored: saved as pending; ProcessFeedbackAnalysis runs after the
 *    response is sent (no queue worker needed).
 *  - duplicate: the same normalized text for the same listing inside
 *    config('tourist_feedback.duplicate_window_minutes') — not saved
 *    again, so it is never counted twice.
 *  - honeypot: saved as rejected with a reason and never analyzed, so it
 *    is excluded from analytics.
 * No IP address, account, or location is stored.
 */
class FeedbackSubmissionService
{
    public const OUTCOME_STORED = 'stored';

    public const OUTCOME_DUPLICATE = 'duplicate';

    public const OUTCOME_HONEYPOT = 'honeypot';

    public const REASON_HONEYPOT = 'Automated submission (honeypot field filled).';

    public function __construct(private readonly FeedbackTextTokenizer $objTokenizer) {}

    /**
     * @param  array<string, mixed>  $arrValidated  StoreTouristFeedbackRequest::validated()
     */
    public function submit(Listing $objListing, array $arrValidated): string
    {
        $strHash = $this->contentHash($objListing, (string) $arrValidated['feedback']);
        $blnIsHoneypot = trim((string) ($arrValidated['website'] ?? '')) !== '';

        // A lock on the content hash closes the double-click gap: a second
        // identical request arriving while the first is being saved is
        // treated as the duplicate it is.
        $objLock = Cache::lock('tourist_feedback_submission:'.$strHash, 10);

        if (! $objLock->get()) {
            return self::OUTCOME_DUPLICATE;
        }

        try {
            if ($this->isDuplicate($objListing, $strHash)) {
                return self::OUTCOME_DUPLICATE;
            }

            $objFeedback = $this->_store($objListing, $arrValidated, $strHash, $blnIsHoneypot);
        } finally {
            $objLock->release();
        }

        if ($blnIsHoneypot) {
            return self::OUTCOME_HONEYPOT;
        }

        ProcessFeedbackAnalysis::dispatchAfterResponse($objFeedback->fbk_id);

        return self::OUTCOME_STORED;
    }

    /**
     * SHA-256 of the listing and the normalized words of the text, so case,
     * punctuation, and spacing changes do not defeat duplicate detection.
     */
    public function contentHash(Listing $objListing, string $strText): string
    {
        return hash('sha256', $objListing->lst_id.'|'.implode(' ', $this->objTokenizer->tokenize($strText)));
    }

    /**
     * Whether the same normalized text was already submitted for this
     * listing inside the duplicate window.
     */
    public function isDuplicate(Listing $objListing, string $strHash): bool
    {
        return Feedback::query()
            ->where('lst_id', $objListing->lst_id)
            ->where('fbk_content_hash', $strHash)
            ->where('fbk_created_at', '>=', now()->subMinutes((int) config('tourist_feedback.duplicate_window_minutes')))
            ->exists();
    }

    /**
     * Tourist input through the fillable fields; consent time, hash, and
     * (for a honeypot hit) the rejected status are set by the system.
     *
     * @param  array<string, mixed>  $arrValidated
     */
    private function _store(Listing $objListing, array $arrValidated, string $strHash, bool $blnIsHoneypot): Feedback
    {
        $strTouristName = trim((string) ($arrValidated['tourist_name'] ?? ''));
        $objFeedback = $objListing->feedbacks()->make([
            'fbk_original_text' => (string) $arrValidated['feedback'],
            'fbk_tourist_name' => $strTouristName !== '' ? $strTouristName : null,
            'fbk_visit_date' => $arrValidated['visit_date'] ?? null,
        ]);
        $arrSystemFields = ['fbk_consent_at' => now(), 'fbk_content_hash' => $strHash];

        if ($blnIsHoneypot) {
            $arrSystemFields['fbk_status'] = FeedbackAnalysisStatus::Rejected;
            $arrSystemFields['fbk_failure_reason'] = self::REASON_HONEYPOT;
        }

        $objFeedback->forceFill($arrSystemFields)->save();

        return $objFeedback;
    }
}
