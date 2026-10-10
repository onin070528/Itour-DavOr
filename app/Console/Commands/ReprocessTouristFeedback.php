<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Runs the tourist feedback analysis again for pending and/or
 * failed feedback (for example after a translation outage, or once the
 * translation key is configured). Analyzed and rejected rows are never
 * touched.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Console\Commands;

use App\Enums\FeedbackAnalysisStatus;
use App\Models\Feedback;
use App\Services\FeedbackAnalysisService;
use Illuminate\Console\Command;

class ReprocessTouristFeedback extends Command
{
    protected $signature = 'feedback:reprocess
        {--status=all : Which rows to process: pending, failed, or all}
        {--limit=0 : Process at most this many rows (0 = no limit)}';

    protected $description = 'Re-runs the tourist feedback analysis (translation, sentiment, issues) for pending and/or failed feedback. Analyzed and rejected feedback is never changed.';

    public function handle(FeedbackAnalysisService $objAnalysis): int
    {
        $strStatus = (string) $this->option('status');
        $intLimit = max(0, (int) $this->option('limit'));
        $arrStatuses = match ($strStatus) {
            'pending' => [FeedbackAnalysisStatus::Pending->value],
            'failed' => [FeedbackAnalysisStatus::Failed->value],
            'all' => [FeedbackAnalysisStatus::Pending->value, FeedbackAnalysisStatus::Failed->value],
            default => null,
        };

        if ($arrStatuses === null) {
            $this->error('--status must be pending, failed, or all.');

            return self::INVALID;
        }

        $objQuery = Feedback::query()->whereIn('fbk_status', $arrStatuses)->orderBy('fbk_id');

        if ($intLimit > 0) {
            $objQuery->limit($intLimit);
        }

        $arrTotals = ['analyzed' => 0, 'failed' => 0, 'rejected' => 0];

        foreach ($objQuery->get() as $objFeedback) {
            $strOutcome = $objAnalysis->process($objFeedback)->fbk_status->value;
            $arrTotals[$strOutcome] = ($arrTotals[$strOutcome] ?? 0) + 1;
        }

        $intProcessed = array_sum($arrTotals);
        $this->info("Processed {$intProcessed} feedback: {$arrTotals['analyzed']} analyzed, {$arrTotals['failed']} failed, {$arrTotals['rejected']} rejected.");

        return self::SUCCESS;
    }
}
