<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Public tourist feedback (Objective 4): the feedback form, its
 * submission, and the neutral thank-you page. Tourists never see a
 * sentiment result, score, issue, recommendation, or translation.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Http\Requests\StoreTouristFeedbackRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Services\FeedbackSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TouristFeedbackController extends Controller
{
    /**
     * The feedback form. `?listing={slug}` preselects a listing (the
     * "Share Your Experience" button); an unknown or ineligible slug only
     * shows a notice and the full selector, never an error page.
     */
    public function create(Request $objRequest): View
    {
        $colListings = Listing::query()
            ->acceptingFeedback()
            ->with('categoryRecord')
            ->orderBy('lst_name')
            ->get(['lst_id', 'lst_slug', 'lst_name', 'lst_municipality', 'lst_category', 'cat_id']);
        $strRequestedSlug = (string) $objRequest->query('listing', '');
        $objSelectedListing = $strRequestedSlug !== '' ? $colListings->firstWhere('lst_slug', $strRequestedSlug) : null;

        return view('feedback.create', [
            'listingGroups' => $this->_groupListings($colListings),
            'selectedListing' => $objSelectedListing,
            'isRequestedListingUnavailable' => $strRequestedSlug !== '' && $objSelectedListing === null,
            'maxVisitDate' => today()->toDateString(),
        ]);
    }

    /**
     * Stores the validated submission and always answers with the same
     * thank-you page (stored, duplicate, or honeypot alike).
     */
    public function store(StoreTouristFeedbackRequest $objRequest, FeedbackSubmissionService $objSubmission): RedirectResponse
    {
        $objSubmission->submit($objRequest->eligibleListing(), $objRequest->validated());

        return redirect()->route('feedback.thankYou');
    }

    public function thankYou(): View
    {
        return view('feedback.thank_you');
    }

    /**
     * Option groups for the selector: Tourist Destinations first, then each
     * establishment category in its configured order. Only public fields
     * (slug, name, municipality) reach the view.
     *
     * @param  Collection<int, Listing>  $colListings
     * @return array<string, array<int, array{slug: string, name: string, municipality: string}>>
     */
    private function _groupListings(Collection $colListings): array
    {
        $arrCategoryOrder = Category::query()->orderBy('cat_sort_order')->pluck('cat_name')->all();
        $arrGroups = [];

        foreach ($colListings as $objListing) {
            $arrGroups[$objListing->categoryName()][] = [
                'slug' => $objListing->lst_slug,
                'name' => $objListing->lst_name,
                'municipality' => (string) $objListing->lst_municipality,
            ];
        }

        uksort($arrGroups, function (string $strFirst, string $strSecond) use ($arrCategoryOrder) {
            $intFirst = array_search($strFirst, $arrCategoryOrder, true);
            $intSecond = array_search($strSecond, $arrCategoryOrder, true);

            return ($intFirst === false ? PHP_INT_MAX : $intFirst) <=> ($intSecond === false ? PHP_INT_MAX : $intSecond);
        });

        return $arrGroups;
    }
}
