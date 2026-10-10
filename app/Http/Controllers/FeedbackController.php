<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Public tourist feedback submission for a destination or establishment.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Models\Listing;
use App\Services\SentimentAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class FeedbackController extends Controller
{
    /**
     * Anyone may leave feedback on a publicly visible listing (the same
     * rule that makes its page reachable). Sentiment, polarity and
     * language are computed once here and stored with the row.
     */
    public function store(StoreFeedbackRequest $objRequest, Listing $listing, SentimentAnalyzer $objAnalyzer): RedirectResponse
    {
        abort_unless($listing->isPubliclyVisible(), 404);

        $arrData = $objRequest->validated();
        $arrAnalysis = $objAnalyzer->analyze($arrData['comment'], (int) $arrData['rating']);

        try {
            Feedback::query()->create([
                'lst_id' => $listing->lst_id,
                'fbk_name' => filled($arrData['name'] ?? null) ? trim($arrData['name']) : null,
                'fbk_rating' => (int) $arrData['rating'],
                'fbk_text' => trim($arrData['comment']),
                'fbk_language' => $arrAnalysis['language'],
                'fbk_sentiment' => $arrAnalysis['sentiment'],
                'fbk_polarity' => $arrAnalysis['polarity'],
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to save tourist feedback.', ['exception' => $objException, 'listing_id' => $listing->lst_id]);

            return back()->withInput()->with('toast', 'Something went wrong while sending your feedback. Please try again.')->with('toast_tone', 'danger');
        }

        return redirect()->to(route('listings.show', $listing).'#feedback')->with('toast', 'Thank you! Your feedback was sent.');
    }
}
