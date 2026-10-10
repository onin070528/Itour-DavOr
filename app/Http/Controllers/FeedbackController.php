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
use App\Models\Listing;
use App\Models\QrFeedback;
use App\Services\SentimentAnalyzer;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    /**
     * The formal feedback form reached by scanning a listing's own feedback
     * QR code. {listing} is the listing's lst_uuid — each destination or
     * establishment has its own feedback QR, separate from its check-in QR.
     * An open listing's scan goes on to the public feedback form
     * (TouristFeedbackController), which stores into the feedback analytics
     * pipeline the LGU, PTO and establishment feedback pages read — so the
     * QR codes already printed keep working. A closed listing still gets the
     * "not accepting feedback" notice.
     */
    public function show(string $listing): View|RedirectResponse
    {
        $objListing = Listing::query()->where('lst_uuid', $listing)->firstOrFail();

        if ($objListing->isFeedbackQrEnabled()) {
            return redirect()->route('feedback.create', ['listing' => $objListing->lst_slug]);
        }

        return view('feedback.form', [
            'listing' => $objListing,
            'isOpen' => $objListing->isFeedbackQrEnabled(),
        ]);
    }

    /**
     * Submits the formal form. Redirects back to it with a thank-you state.
     */
    public function submit(StoreFeedbackRequest $objRequest, string $listing, SentimentAnalyzer $objAnalyzer): RedirectResponse
    {
        $objListing = Listing::query()->where('lst_uuid', $listing)->firstOrFail();
        abort_unless($objListing->isFeedbackQrEnabled(), 404);

        if (! $this->persist($objRequest, $objListing, $objAnalyzer)) {
            return back()->withInput()->with('toast', 'Something went wrong while sending your feedback. Please try again.')->with('toast_tone', 'danger');
        }

        return redirect()->route('feedback.form', $listing)->with('feedback_sent', true);
    }

    /**
     * Anyone may leave feedback on a publicly visible listing (the same
     * rule that makes its page reachable). Sentiment, polarity and
     * language are computed once here and stored with the row.
     */
    public function store(StoreFeedbackRequest $objRequest, Listing $listing, SentimentAnalyzer $objAnalyzer): RedirectResponse
    {
        abort_unless($listing->isPubliclyVisible(), 404);

        if (! $this->persist($objRequest, $listing, $objAnalyzer)) {
            return back()->withInput()->with('toast', 'Something went wrong while sending your feedback. Please try again.')->with('toast_tone', 'danger');
        }

        return redirect()->to(route('listings.show', $listing).'#feedback')->with('toast', 'Thank you! Your feedback was sent.');
    }

    private function persist(StoreFeedbackRequest $objRequest, Listing $objListing, SentimentAnalyzer $objAnalyzer): bool
    {
        $arrData = $objRequest->validated();
        $arrAnalysis = $objAnalyzer->analyze($arrData['comment'], (int) $arrData['rating']);
        $arrAspects = collect($arrData['aspects'] ?? [])->filter()->map(fn ($mixRating) => (int) $mixRating)->all();

        try {
            QrFeedback::query()->create([
                'lst_id' => $objListing->lst_id,
                'fbk_name' => filled($arrData['name'] ?? null) ? trim($arrData['name']) : null,
                'fbk_email' => filled($arrData['email'] ?? null) ? trim($arrData['email']) : null,
                'fbk_visit_date' => $arrData['visit_date'] ?? null,
                'fbk_visit_purpose' => $arrData['visit_purpose'] ?? null,
                'fbk_visitor_origin' => $arrData['visitor_origin'] ?? null,
                'fbk_rating' => (int) $arrData['rating'],
                'fbk_aspect_ratings' => $arrAspects ?: null,
                'fbk_would_recommend' => isset($arrData['would_recommend']) ? (bool) $arrData['would_recommend'] : null,
                'fbk_text' => trim($arrData['comment']),
                'fbk_language' => $arrAnalysis['language'],
                'fbk_sentiment' => $arrAnalysis['sentiment'],
                'fbk_polarity' => $arrAnalysis['polarity'],
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to save tourist feedback.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            return false;
        }

        $strMessage = "New {$arrData['rating']}-star feedback received for {$objListing->lst_name}.";
        Notifier::toEstablishment($objListing, 'feedback', $strMessage, route('establishment.feedback.index'), 'ti-message-2');
        Notifier::toLgu($objListing->mun_id, 'feedback', $strMessage, route('lgu.feedback.index'), 'ti-message-2');

        return true;
    }
}
