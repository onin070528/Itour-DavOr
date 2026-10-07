<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the PTO Tourism Monitoring Dashboard — KPIs, arrival
 * trend, LGU visitation statistics, reporting status, and visitor
 * classification, aggregated from real Verified MonthlyArrivalReport /
 * MunicipalReport data via App\Support\TourismAnalytics. PTO watches and
 * validates this data; it never re-encodes it.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Models\Listing;
use App\Models\Municipality;
use App\Support\TourismAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends PtoController
{
    /**
     * The PTO landing page: the province-wide Tourism Monitoring Dashboard.
     */
    public function index(Request $objRequest): View
    {
        $arrFilters = TourismAnalytics::resolveFilters($objRequest);
        $arrComparison = TourismAnalytics::periodComparison($arrFilters);

        $objMunicipalities = Municipality::query()->orderBy('mun_name')->get();

        // The Establishment filter only ever lists records that can
        // actually collect arrivals — same single source of truth as the
        // Tourism Directory (Listing::isQrEnabled()).
        $objEstablishments = Listing::query()
            ->with('categoryRecord')
            ->when($arrFilters['municipalityId'], fn ($objQuery, $intId) => $objQuery->where('mun_id', $intId))
            ->orderBy('lst_name')
            ->get()
            ->filter(fn (Listing $objListing) => $objListing->isQrEnabled())
            ->values();

        $objReportingStatus = TourismAnalytics::reportingStatus($arrFilters);
        $intNotYetReportedCount = $objReportingStatus->where('status', '!=', 'Verified')->count();

        return $this->renderPto($objRequest, 'pto.dashboard', 'dashboard', 'Tourism Monitoring Dashboard', [
            'filters' => $arrFilters,
            'yearOptions' => TourismAnalytics::yearOptions(),
            'municipalities' => $objMunicipalities,
            'establishments' => $objEstablishments,
            'kpis' => TourismAnalytics::kpis($arrFilters, $arrComparison),
            'arrivalTrend' => TourismAnalytics::arrivalTrend($arrFilters),
            'municipalityComparison' => TourismAnalytics::municipalityComparison($arrFilters),
            'reportingStatus' => $objReportingStatus,
            'notYetReportedCount' => $intNotYetReportedCount,
            'classification' => TourismAnalytics::classificationBreakdown($arrFilters),
            'recentActivity' => TourismAnalytics::recentActivity($arrFilters['municipalityId']),
        ]);
    }
}
