<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Serves an establishment's check-in QR code (view / download / print poster) and its on/off switch to the roles allowed.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\QrCodeService;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Shared by every role (one route set, not one per role prefix) because
 * ListingPolicy already decides who may do what: viewQr() — the
 * establishment its own QR, the LGU its own municipality's, the PTO all
 * (read-only); manageQr() — the on/off switch, establishment and LGU only.
 * A listing that is not accepting registrations has no QR to view, so it
 * 404s rather than serving a dead code.
 */
class QrCodeController extends Controller
{
    /** Vector output, so this only sets the SVG's coordinate size, not print sharpness. */
    private const POSTER_QR_SIZE = 512;

    public function __construct(private QrCodeService $qrCodeService) {}

    /**
     * The QR code as an inline SVG image (usable as an <img src>).
     *
     * $listing — not $objListing — because route-model binding matches the
     * parameter to its route segment by name (framework-required).
     */
    public function show(Request $objRequest, Listing $listing): Response
    {
        $strSvg = $this->_resolveSvg($objRequest, $listing);

        return response($strSvg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The same QR code as a downloadable .svg file named after the listing.
     */
    public function download(Request $objRequest, Listing $listing): Response
    {
        $strSvg = $this->_resolveSvg($objRequest, $listing);
        $strFileName = str($listing->name)->slug()->append('-qr-code.svg')->toString();

        return response($strSvg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$strFileName.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Printable poster around the QR code: an A4 poster (default) or, with
     * ?layout=card, a smaller A6 table card with cut lines — both on A4
     * paper, printed (or saved as PDF) from the browser. Always shows the
     * listing's current name and its uuid check-in URL.
     */
    public function poster(Request $objRequest, Listing $listing): View
    {
        $strSvg = $this->_resolveSvg($objRequest, $listing, self::POSTER_QR_SIZE);
        $strLayout = $objRequest->query('layout') === 'card' ? 'card' : 'a4';
        $strCheckinUrl = $this->qrCodeService->buildCheckinUrl($listing);

        return view('establishment.qr-poster', [
            'establishmentName' => $listing->name,
            'municipalityName' => $listing->municipality,
            'qrSvg' => $strSvg,
            'checkinUrlLabel' => preg_replace('#^https?://#', '', $strCheckinUrl),
            'layout' => $strLayout,
            'posterUrl' => route('qrCodes.poster', $listing),
            'cardUrl' => route('qrCodes.poster', ['listing' => $listing, 'layout' => 'card']),
            'downloadUrl' => route('qrCodes.download', $listing),
        ]);
    }

    /**
     * Switches this establishment's own QR check-in on or off
     * (ListingPolicy::manageQr()). The QR code itself never changes — the
     * same printed code works again once switched back on — and existing
     * arrivals are untouched. Every actual change is audit-logged with its
     * old and new value; repeating the current state changes nothing.
     */
    public function updateStatus(Request $objRequest, Listing $listing): RedirectResponse
    {
        abort_unless($objRequest->user()->can('manageQr', $listing), 403);

        $arrValidated = $objRequest->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $blnIsEnabled = (bool) $arrValidated['is_enabled'];
        $blnIsUnchanged = $listing->lst_is_qr_enabled === $blnIsEnabled;

        if ($blnIsUnchanged) {
            return back()->with('toast', $blnIsEnabled
                ? "QR check-in is already on for {$listing->name}."
                : "QR check-in is already off for {$listing->name}.");
        }

        $arrBefore = $listing->getOriginal();

        try {
            $listing->forceFill(['lst_is_qr_enabled' => $blnIsEnabled])->save();

            OperationLogger::updated(
                $objRequest->user(),
                'establishment',
                $listing->id,
                $listing->municipality_id,
                $listing->id,
                OperationLogger::diff($arrBefore, $listing),
                $blnIsEnabled ? 'QR check-in switched on' : 'QR check-in switched off'
            );
        } catch (Throwable $errUpdate) {
            Log::error('Failed to change QR check-in status.', ['exception' => $errUpdate, 'listing_id' => $listing->id]);

            return back()->with('toast', 'The QR check-in setting could not be saved. Please try again.');
        } // end try update

        return back()->with('toast', $blnIsEnabled
            ? "QR check-in is on again for {$listing->name}. The same QR code works again."
            : "QR check-in is off for {$listing->name}. Scans now show \"not accepting registrations\"; existing arrivals are untouched.");
    }

    /**
     * Authorization (403) first, so a user can never learn anything about
     * a listing outside their scope; then 404 when the listing has no
     * usable QR; then 503 with a friendly message if generation fails.
     */
    private function _resolveSvg(Request $objRequest, Listing $listing, int $intSize = QrCodeService::DEFAULT_SIZE): string
    {
        abort_unless($objRequest->user()->can('viewQr', $listing), 403);
        abort_unless($listing->isAcceptingRegistrations(), 404);

        $strSvg = $this->qrCodeService->generateSvg($listing, $intSize);

        abort_if($strSvg === null, 503, 'The QR code could not be generated right now. Please try again in a moment.');

        return $strSvg;
    }
}
