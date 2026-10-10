<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Runs the tourist feedback analysis for one stored feedback row.
 * The public submission (a later phase) dispatches it with
 * dispatchAfterResponse(), so the tourist gets the thank-you page at once
 * and no queue worker is needed on the server.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Jobs;

use App\Models\Feedback;
use App\Services\FeedbackAnalysisService;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessFeedbackAnalysis
{
    use Dispatchable;

    /**
     * Holds only the id: the row is read fresh when the job runs.
     */
    public function __construct(public readonly int $intFeedbackId) {}

    public function handle(FeedbackAnalysisService $objAnalysis): void
    {
        $objFeedback = Feedback::query()->find($this->intFeedbackId);

        if ($objFeedback === null) {
            return;
        }

        $objAnalysis->process($objFeedback);
    }
}
