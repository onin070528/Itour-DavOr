<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : PTO destination listing review — the queue of LGU requests (and changes to Published listings) and the review screen.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\ImageStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Read-only screens: the decisions themselves stay on the existing
 * Pto\DirectoryController::publish() ("Approve & Publish") and
 * ::returnToLgu() ("Return for Correction", remarks required), both gated
 * by ListingPolicy::publish() — so there is still exactly one publish path.
 * Reached from the PTO notification bell and the Tourism Directory; the
 * PTO sidebar is unchanged.
 */
class DestinationReviewsController extends PtoController
{
    /**
     * Field labels for the review screen, in display order.
     *
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'lst_name' => 'Name',
        'cat_id' => 'Category',
        'lst_type' => 'Type',
        'lst_category_note' => 'Category note',
        'lst_barangay' => 'Barangay / Address',
        'lst_lat' => 'Latitude',
        'lst_lng' => 'Longitude',
        'lst_description' => 'Description',
        'lst_visitor_information' => 'Visitor information',
        'lst_entrance_fee' => 'Entrance fee',
    ];

    /**
     * The review queue: what the PTO has to decide (new requests and
     * changes to Published listings), and what is back with the LGUs.
     */
    public function index(Request $request): View
    {
        $objAwaiting = Listing::query()
            ->awaitingPtoDecision()
            ->with('categoryRecord')
            ->orderBy('lst_updated_at')
            ->get();

        // Summary comment: establishments and destination-only records alike
        // (a returned request, or returned held changes to a live listing).
        $objReturned = Listing::query()
            ->returnedForCorrection()
            ->with('categoryRecord')
            ->orderByDesc('lst_updated_at')
            ->get();

        return $this->renderPto($request, 'pto.destination-reviews.index', 'directory', 'Destination Listing Reviews', [
            'awaitingListings' => $objAwaiting,
            'returnedListings' => $objReturned,
        ]);
    } // end index

    /**
     * Review screen: name, description, photos, location, municipality,
     * category/type, and contact details; for changes to a Published
     * listing, the live value beside the proposed one. Approve & Publish
     * and Return for Correction appear only while the PTO has something
     * to decide.
     */
    public function show(Request $request, Listing $listing): View
    {
        abort_unless($request->user()->can('publish', $listing), 403);

        $listing->load(['categoryRecord', 'establishmentImages']);

        // Summary comment: the workflow logs a destination-only record as
        // 'destination' and an establishment as 'establishment'
        // (Listing::auditEntityType()), so read the listing's own type.
        $objHistory = OperationLog::query()
            ->with('user')
            ->where('opl_entity_type', $listing->auditEntityType())
            ->where('opl_entity_id', $listing->lst_id)
            ->whereIn('opl_action', ['submit', 'publish', 'return', 'unpublish'])
            ->latest('opl_id')
            ->limit(10)
            ->get();

        return $this->renderPto($request, 'pto.destination-reviews.show', 'directory', "Review {$listing->lst_name}", [
            'listing' => $listing,
            'changeRows' => $listing->hasPendingChanges() ? $this->_changeRows($listing) : collect(),
            'publishedImages' => $listing->establishmentImages->where('img_status', ImageStatus::Published)->values(),
            'pendingImageCount' => $listing->establishmentImages->where('img_status', ImageStatus::Pending)->count(),
            'lastSubmission' => $objHistory->firstWhere('opl_action', 'submit'),
            'history' => $objHistory,
            'blnIsAwaitingDecision' => $listing->isAwaitingPtoDecision(),
        ]);
    } // end show

    /**
     * One row per held change: the field, its live value, and the
     * proposed value, both in display form.
     *
     * @return Collection<int, array{label: string, current: string, proposed: string}>
     */
    private function _changeRows(Listing $objListing): Collection
    {
        $arrPending = $objListing->lst_pending_changes;

        return collect(self::FIELD_LABELS)
            ->filter(fn (string $strLabel, string $strField) => array_key_exists($strField, $arrPending))
            ->map(fn (string $strLabel, string $strField) => [
                'label' => $strLabel,
                'current' => $this->_displayValue($strField, $objListing->getAttribute($strField)),
                'proposed' => $this->_displayValue($strField, $arrPending[$strField]),
            ])
            ->values();
    } // end _changeRows

    private function _displayValue(string $strField, mixed $mixValue): string
    {
        if ($mixValue === null || $mixValue === '') {
            return '—';
        }

        if ($strField === 'cat_id') {
            return Category::query()->find($mixValue)?->cat_name ?? (string) $mixValue;
        }

        return (string) $mixValue;
    } // end _displayValue
}
