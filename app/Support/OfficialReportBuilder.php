<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Builds the generic data shape resources/views/pdf/official-report
 * .blade.php renders from, and validates that each row's Male/Female,
 * Adults/Children/Seniors, and Local/Foreign column groups all add up to
 * that row's Total before a report is allowed to generate — shared by the
 * LGU Submissions (municipal) and Provincial Reports PDFs so neither
 * re-implements the layout or the validation rule. Also builds the data
 * for a saved MunicipalReport (PTO and LGU views alike), the LGU's live,
 * not-yet-submitted municipal consolidation, and a single establishment's
 * monthly report — all rendered by the same A4 template.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Http\Controllers\Pto\MunicipalReportsController;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class OfficialReportBuilder
{
    /**
     * The establishment breakdown of a saved MunicipalReport (QR-enabled
     * records only — Listing::isQrEnabled() is the single source of truth)
     * and the QR-enabled establishments in its municipality that are not in
     * it. Moved here unchanged from Pto\MunicipalReportsController so the
     * LGU's municipal views render exactly what PTO sees.
     *
     * @return array{0: Collection, 1: Collection}
     */
    public static function municipalBreakdown(MunicipalReport $municipalReport): array
    {
        $municipalReport->loadMissing(['submitter', 'reviewer', 'monthlyArrivalReports.listing.categoryRecord', 'monthlyArrivalReports.submitter', 'monthlyArrivalReports.verifier']);

        $breakdown = $municipalReport->monthlyArrivalReports->filter(
            fn ($monthlyReport) => $monthlyReport->listing?->isQrEnabled()
        )->values();

        $missingEstablishments = $municipalReport->municipality_id
            ? self::_missingEstablishments($municipalReport->municipality_id, $breakdown)
            : collect();

        return [$breakdown, $missingEstablishments];
    } // end municipalBreakdown

    /**
     * @param  Collection<int, MonthlyArrivalReport>  $breakdown
     * @return Collection<int, array<string, mixed>>
     */
    public static function breakdownRows(Collection $breakdown): Collection
    {
        return $breakdown->map(fn ($monthlyReport) => [
            'establishment' => $monthlyReport->listing->name,
            'category' => $monthlyReport->listing->categoryRecord?->cat_name ?? 'Uncategorized',
            'source' => $monthlyReport->submission_source->label(),
            'male' => (int) $monthlyReport->party_male,
            'female' => (int) $monthlyReport->party_female,
            'total' => (int) $monthlyReport->total_visitors,
            'adults' => (int) $monthlyReport->party_adults,
            'children' => (int) $monthlyReport->party_children,
            'seniors' => (int) $monthlyReport->party_seniors,
            'local' => (int) $monthlyReport->party_local,
            'foreign' => (int) $monthlyReport->party_foreign,
        ])->values();
    } // end breakdownRows

    /**
     * Official Report data for a saved MunicipalReport (moved unchanged
     * from Pto\MunicipalReportsController::officialReportData()).
     *
     * @return array<string, mixed>
     */
    public static function fromMunicipalReport(MunicipalReport $municipalReport): array
    {
        [$breakdown, $missingEstablishments] = self::municipalBreakdown($municipalReport);

        return [
            'title' => 'LGU Consolidated Tourism Report',
            'letterhead' => self::_lguLetterhead($municipalReport->municipality),
            'period_label' => $municipalReport->period_start->format('F Y'),
            'reference_number' => sprintf('MRP-%06d', $municipalReport->id),
            'status_label' => MunicipalReportsController::statusLabel($municipalReport->status),
            'is_draft' => ! $municipalReport->isFrozen(),
            'verified_label' => $municipalReport->isFrozen() && $municipalReport->reviewed_at
                ? 'Verified on '.$municipalReport->reviewed_at->format('F j, Y').' by '.($municipalReport->reviewer->name ?? 'PTO Administrator')
                : null,
            'revision_number' => $municipalReport->revision_number,
            'supersedes_reference' => $municipalReport->supersedes_id ? sprintf('MRP-%06d', $municipalReport->supersedes_id) : null,
            ...self::_consolidatedBody($breakdown, $missingEstablishments),
            'remarks' => $municipalReport->remarks,
            'signatures' => [
                'prepared_by' => [
                    'name' => $municipalReport->submitter->name ?? null,
                    'position' => $municipalReport->submitter?->role?->title(),
                    'date' => $municipalReport->created_at->format('M j, Y'),
                ],
                'reviewed_by' => [
                    'name' => $municipalReport->reviewer->name ?? null,
                    'position' => $municipalReport->reviewer?->role?->title(),
                    'date' => $municipalReport->reviewed_at?->format('M j, Y'),
                ],
                'approved_by' => [
                    'name' => $municipalReport->reviewer->name ?? null,
                    'position' => $municipalReport->reviewer?->role?->title(),
                    'date' => $municipalReport->reviewed_at?->format('M j, Y'),
                ],
            ],
            'verification_code' => $municipalReport->verification_code,
            'generated_at' => now()->format('M j, Y g:i A'),
        ];
    } // end fromMunicipalReport

    /**
     * Official Report data for an LGU's municipal report that has not been
     * submitted to PTO yet — computed live from the period's Verified
     * establishment reports only, using the same breakdown rule as a saved
     * report so the preview matches what PTO will receive. Establishments
     * with no Verified report are listed as Not Submitted, never as zero.
     *
     * @param  Collection<int, MonthlyArrivalReport>  $objVerifiedReports
     * @return array<string, mixed>
     */
    public static function fromLiveConsolidation(Municipality $objMunicipality, CarbonInterface $dtMonth, Collection $objVerifiedReports, string $strPreparedBy, ?string $strPreparerPosition): array
    {
        $objVerifiedReports = EloquentCollection::make($objVerifiedReports->all())
            ->loadMissing(['listing.categoryRecord', 'submitter', 'verifier']);

        $objBreakdown = $objVerifiedReports->filter(fn (MonthlyArrivalReport $objReport) => $objReport->listing?->isQrEnabled())->values();
        $objMissing = self::_missingEstablishments($objMunicipality->id, $objBreakdown);

        $strMissingNames = $objMissing->pluck('name')->implode(', ');

        return [
            'title' => 'LGU Consolidated Tourism Report',
            'letterhead' => self::_lguLetterhead($objMunicipality->name),
            'period_label' => $dtMonth->format('F Y'),
            'reference_number' => 'Not yet submitted',
            'status_label' => 'Not yet submitted to PTO',
            'is_draft' => true,
            'verified_label' => null,
            'revision_number' => 1,
            'supersedes_reference' => null,
            ...self::_consolidatedBody($objBreakdown, $objMissing),
            'remarks' => $strMissingNames !== ''
                ? "No verified report (Not Submitted, not counted as zero): {$strMissingNames}."
                : null,
            'signatures' => [
                'prepared_by' => ['name' => $strPreparedBy, 'position' => $strPreparerPosition, 'date' => null],
                'reviewed_by' => ['name' => null, 'position' => 'Provincial Tourism Office', 'date' => null],
                'approved_by' => ['name' => null, 'position' => 'Provincial Tourism Office', 'date' => null],
            ],
            'verification_code' => null,
            'generated_at' => now()->format('M j, Y g:i A'),
        ];
    } // end fromLiveConsolidation

    /**
     * Official Report data for one establishment's monthly tourist-arrival
     * report. Provisional layout until the official paper template is
     * provided: uses only fields iTOUR already stores.
     *
     * @return array<string, mixed>
     */
    public static function fromMonthlyArrivalReport(MonthlyArrivalReport $objReport): array
    {
        $objReport->loadMissing(['listing.categoryRecord', 'municipality', 'submitter', 'verifier']);

        $objListing = $objReport->listing;
        $arrOrigin = $objReport->originBreakdown();
        $blnIsVerified = $objReport->verified_at !== null && $objReport->status === MonthlyReportStatus::Verified;

        $arrDetails = [
            'Establishment' => $objListing->name,
            'Category' => $objListing->categoryRecord?->cat_name ?? 'Uncategorized',
            'Address' => trim($objListing->barangay.', '.$objListing->municipality, ', '),
            'Report Source' => $objReport->submission_source->label(),
            ($objReport->submission_source === ReportSubmissionSource::ManualPaper ? 'Encoded by' : 'Submitted by') => $objReport->submitter
                ? $objReport->submitter->name.' ('.$objReport->submitter->role?->title().')'
                : 'Not yet submitted',
            'Date Submitted' => $objReport->submitted_at?->format('F j, Y g:i A') ?? '—',
        ];

        if ($arrOrigin['withinProvince'] > 0 || $arrOrigin['outsideProvince'] > 0) {
            $arrDetails['Local Guests — Within / Outside Davao Oriental'] = number_format($arrOrigin['withinProvince']).' / '.number_format($arrOrigin['outsideProvince']);
        }

        if ($arrOrigin['topOriginPlaces']->isNotEmpty()) {
            $arrDetails['Top Origin Provinces'] = $arrOrigin['topOriginPlaces']->map(fn (int $intCount, string $strPlace) => "{$strPlace} ({$intCount})")->implode(', ');
        }

        if ($arrOrigin['topForeignCountries']->isNotEmpty()) {
            $arrDetails['Top Foreign Countries'] = $arrOrigin['topForeignCountries']->map(fn (int $intCount, string $strCountry) => "{$strCountry} ({$intCount})")->implode(', ');
        }

        $objRows = self::breakdownRows(collect([$objReport]));

        return [
            'title' => 'Monthly Tourist Arrival Report',
            'letterhead' => self::_lguLetterhead($objReport->municipality?->name ?? $objListing->municipality),
            'period_label' => $objReport->period_month->format('F Y'),
            'reference_number' => sprintf('MAR-%06d', $objReport->id),
            'status_label' => $objReport->status->label(),
            'is_draft' => ! $blnIsVerified,
            'verified_label' => $blnIsVerified
                ? 'Verified on '.$objReport->verified_at->format('F j, Y').' by '.($objReport->verifier->name ?? 'LGU Tourism Office')
                : null,
            'revision_number' => 1,
            'supersedes_reference' => null,
            'submission_summary' => $objReport->submission_source->label(),
            'details' => $arrDetails,
            'groups' => self::groupByCategory($objRows),
            'grand_total' => self::sumRows($objRows),
            'remarks' => $objReport->remarks,
            'signatures' => [
                'prepared_by' => [
                    'name' => $objReport->submitter->name ?? null,
                    'position' => $objReport->submitter?->role?->title() ?? 'Prepared by',
                    'date' => $objReport->submitted_at?->format('M j, Y'),
                ],
                'reviewed_by' => [
                    'name' => $objReport->verifier->name ?? null,
                    'position' => $objReport->verifier?->role?->title() ?? 'Verified by (LGU)',
                    'date' => $objReport->verified_at?->format('M j, Y'),
                ],
                'approved_by' => ['name' => null, 'position' => 'Noted by', 'date' => null],
            ],
            'verification_code' => null,
            'generated_at' => now()->format('M j, Y g:i A'),
        ];
    } // end fromMonthlyArrivalReport

    /**
     * Shared consolidated-report body: submission summary, category groups,
     * and grand total. Digital vs paper is counted from the enum (the old
     * 'digital' string comparison never matched, always reporting 0 digital).
     *
     * @return array{submission_summary: string, groups: Collection, grand_total: array<string, int>}
     */
    private static function _consolidatedBody(Collection $objBreakdown, Collection $objMissing): array
    {
        $objRows = self::breakdownRows($objBreakdown);
        $intDigitalCount = $objBreakdown->filter(fn ($objReport) => $objReport->submission_source === ReportSubmissionSource::Digital)->count();
        $intPaperCount = $objBreakdown->count() - $intDigitalCount;
        $intTotalEstablishments = $objBreakdown->count() + $objMissing->count();

        return [
            'submission_summary' => "{$objBreakdown->count()} of {$intTotalEstablishments} establishments reported ({$intDigitalCount} digital, {$intPaperCount} paper)",
            'groups' => self::groupByCategory($objRows),
            'grand_total' => self::sumRows($objRows),
        ];
    } // end _consolidatedBody

    /**
     * QR-enabled establishments in the municipality that are not part of
     * the given breakdown.
     */
    private static function _missingEstablishments(int $intMunicipalityId, Collection $objBreakdown): Collection
    {
        return Listing::query()
            ->with('categoryRecord')
            ->where('municipality_id', $intMunicipalityId)
            ->get()
            ->filter(fn (Listing $listing) => $listing->isQrEnabled() && ! $objBreakdown->contains(fn ($r) => $r->listing_id === $listing->id))
            ->values();
    } // end _missingEstablishments

    /**
     * @return array{office_name: string, office_subtitle: string, address: string}
     */
    private static function _lguLetterhead(?string $strMunicipalityName): array
    {
        return [
            'office_name' => $strMunicipalityName.' Tourism Office',
            'office_subtitle' => 'Local Government Unit, Province of Davao Oriental',
            'address' => 'Davao Oriental, Philippines',
        ];
    }

    // end _lguLetterhead
    /**
     * @param  Collection<int, array<string, mixed>>  $rows  Each row must carry
     *                                                       'establishment', 'category', 'male', 'female', 'total', 'adults',
     *                                                       'children', 'seniors', 'local', 'foreign'.
     * @return array<int, string> One message per mismatched row/total — empty
     *                            when every group balances, which is the only time generation may
     *                            proceed.
     */
    public static function validateColumnSums(Collection $rows): array
    {
        $errors = [];

        foreach ($rows as $row) {
            $errors = [...$errors, ...self::validateRow($row['establishment'], $row)];
        }

        $errors = [...$errors, ...self::validateRow('Grand Total', self::sumRows($rows))];

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private static function validateRow(string $label, array $row): array
    {
        $errors = [];

        if ($row['total'] !== $row['male'] + $row['female']) {
            $errors[] = "{$label}: Male + Female (".($row['male'] + $row['female']).") does not equal Total ({$row['total']}).";
        }

        if ($row['total'] !== $row['adults'] + $row['children'] + $row['seniors']) {
            $errors[] = "{$label}: Adults + Children + Seniors (".($row['adults'] + $row['children'] + $row['seniors']).") does not equal Total ({$row['total']}).";
        }

        if ($row['total'] !== $row['local'] + $row['foreign']) {
            $errors[] = "{$label}: Local + Foreign (".($row['local'] + $row['foreign']).") does not equal Total ({$row['total']}).";
        }

        return $errors;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public static function sumRows(Collection $rows): array
    {
        return [
            'male' => (int) $rows->sum('male'),
            'female' => (int) $rows->sum('female'),
            'total' => (int) $rows->sum('total'),
            'adults' => (int) $rows->sum('adults'),
            'children' => (int) $rows->sum('children'),
            'seniors' => (int) $rows->sum('seniors'),
            'local' => (int) $rows->sum('local'),
            'foreign' => (int) $rows->sum('foreign'),
        ];
    }

    /**
     * Groups already-row-shaped data by category, each with its own subtotal
     * row, for the "table of establishments grouped by category with
     * subtotals and a grand total" layout.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<string, array{rows: Collection, subtotal: array}>
     */
    public static function groupByCategory(Collection $rows): Collection
    {
        return $rows->groupBy('category')->map(fn (Collection $group) => [
            'rows' => $group,
            'subtotal' => self::sumRows($group),
        ])->sortKeys();
    }

    /**
     * A short, URL-safe, hard-to-guess code for the public verification
     * page — collision risk is negligible at this volume, and the caller
     * (MunicipalReportsController::approve()) only ever assigns one once,
     * at verification time.
     */
    public static function generateVerificationCode(): string
    {
        return 'ITOUR-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    }
}
