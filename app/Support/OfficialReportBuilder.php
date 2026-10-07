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
    public static function municipalBreakdown(MunicipalReport $objMunicipalReport): array
    {
        $objMunicipalReport->loadMissing(['submitter', 'reviewer', 'monthlyArrivalReports.listing.categoryRecord', 'monthlyArrivalReports.submitter', 'monthlyArrivalReports.verifier']);

        $objBreakdown = $objMunicipalReport->monthlyArrivalReports->filter(
            fn ($objMonthlyReport) => $objMonthlyReport->listing?->isQrEnabled()
        )->values();

        $objMissingEstablishments = $objMunicipalReport->mun_id
            ? self::_missingEstablishments($objMunicipalReport->mun_id, $objBreakdown)
            : collect();

        return [$objBreakdown, $objMissingEstablishments];
    } // end municipalBreakdown

    /**
     * @param  Collection<int, MonthlyArrivalReport>  $objBreakdown
     * @return Collection<int, array<string, mixed>>
     */
    public static function breakdownRows(Collection $objBreakdown): Collection
    {
        return $objBreakdown->map(fn ($objMonthlyReport) => [
            'establishment' => $objMonthlyReport->listing->lst_name,
            'category' => $objMonthlyReport->listing->categoryRecord?->cat_name ?? 'Uncategorized',
            'source' => $objMonthlyReport->mar_submission_source->label(),
            'male' => (int) $objMonthlyReport->mar_party_male,
            'female' => (int) $objMonthlyReport->mar_party_female,
            'total' => (int) $objMonthlyReport->mar_total_visitors,
            'adults' => (int) $objMonthlyReport->mar_party_adults,
            'children' => (int) $objMonthlyReport->mar_party_children,
            'seniors' => (int) $objMonthlyReport->mar_party_seniors,
            'local' => (int) $objMonthlyReport->mar_party_local,
            'foreign' => (int) $objMonthlyReport->mar_party_foreign,
        ])->values();
    } // end breakdownRows

    /**
     * Official Report data for a saved MunicipalReport (moved unchanged
     * from Pto\MunicipalReportsController::officialReportData()).
     *
     * @return array<string, mixed>
     */
    public static function fromMunicipalReport(MunicipalReport $objMunicipalReport): array
    {
        [$objBreakdown, $objMissingEstablishments] = self::municipalBreakdown($objMunicipalReport);

        return [
            'title' => 'LGU Consolidated Tourism Report',
            'letterhead' => self::_lguLetterhead($objMunicipalReport->mrp_municipality),
            'period_label' => $objMunicipalReport->mrp_period_start->format('F Y'),
            'reference_number' => sprintf('MRP-%06d', $objMunicipalReport->mrp_id),
            'status_label' => MunicipalReportsController::statusLabel($objMunicipalReport->mrp_status),
            'is_draft' => ! $objMunicipalReport->isFrozen(),
            'verified_label' => $objMunicipalReport->isFrozen() && $objMunicipalReport->mrp_reviewed_at
                ? 'Verified on '.$objMunicipalReport->mrp_reviewed_at->format('F j, Y').' by '.($objMunicipalReport->reviewer->usr_name ?? 'PTO Administrator')
                : null,
            'revision_number' => $objMunicipalReport->mrp_revision_number,
            'supersedes_reference' => $objMunicipalReport->mrp_supersedes_id ? sprintf('MRP-%06d', $objMunicipalReport->mrp_supersedes_id) : null,
            ...self::_consolidatedBody($objBreakdown, $objMissingEstablishments),
            'remarks' => $objMunicipalReport->mrp_remarks,
            'signatures' => [
                'prepared_by' => [
                    'name' => $objMunicipalReport->submitter->usr_name ?? null,
                    'position' => $objMunicipalReport->submitter?->usr_role?->title(),
                    'date' => $objMunicipalReport->mrp_created_at->format('M j, Y'),
                ],
                'reviewed_by' => [
                    'name' => $objMunicipalReport->reviewer->usr_name ?? null,
                    'position' => $objMunicipalReport->reviewer?->usr_role?->title(),
                    'date' => $objMunicipalReport->mrp_reviewed_at?->format('M j, Y'),
                ],
                'approved_by' => [
                    'name' => $objMunicipalReport->reviewer->usr_name ?? null,
                    'position' => $objMunicipalReport->reviewer?->usr_role?->title(),
                    'date' => $objMunicipalReport->mrp_reviewed_at?->format('M j, Y'),
                ],
            ],
            'verification_code' => $objMunicipalReport->mrp_verification_code,
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
        $objMissing = self::_missingEstablishments($objMunicipality->mun_id, $objBreakdown);

        $strMissingNames = $objMissing->pluck('lst_name')->implode(', ');

        return [
            'title' => 'LGU Consolidated Tourism Report',
            'letterhead' => self::_lguLetterhead($objMunicipality->mun_name),
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
        $blnIsVerified = $objReport->mar_verified_at !== null && $objReport->mar_status === MonthlyReportStatus::Verified;

        $arrDetails = [
            'Establishment' => $objListing->lst_name,
            'Category' => $objListing->categoryRecord?->cat_name ?? 'Uncategorized',
            'Address' => trim($objListing->lst_barangay.', '.$objListing->lst_municipality, ', '),
            'Report Source' => $objReport->mar_submission_source->label(),
            ($objReport->mar_submission_source === ReportSubmissionSource::ManualPaper ? 'Encoded by' : 'Submitted by') => $objReport->submitter
                ? $objReport->submitter->usr_name.' ('.$objReport->submitter->usr_role?->title().')'
                : 'Not yet submitted',
            'Date Submitted' => $objReport->mar_submitted_at?->format('F j, Y g:i A') ?? '—',
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
            'letterhead' => self::_lguLetterhead($objReport->municipality?->mun_name ?? $objListing->lst_municipality),
            'period_label' => $objReport->mar_period_month->format('F Y'),
            'reference_number' => sprintf('MAR-%06d', $objReport->mar_id),
            'status_label' => $objReport->mar_status->label(),
            'is_draft' => ! $blnIsVerified,
            'verified_label' => $blnIsVerified
                ? 'Verified on '.$objReport->mar_verified_at->format('F j, Y').' by '.($objReport->verifier->usr_name ?? 'LGU Tourism Office')
                : null,
            'revision_number' => 1,
            'supersedes_reference' => null,
            'submission_summary' => $objReport->mar_submission_source->label(),
            'details' => $arrDetails,
            'groups' => self::groupByCategory($objRows),
            'grand_total' => self::sumRows($objRows),
            'remarks' => $objReport->mar_remarks,
            'signatures' => [
                'prepared_by' => [
                    'name' => $objReport->submitter->usr_name ?? null,
                    'position' => $objReport->submitter?->usr_role?->title() ?? 'Prepared by',
                    'date' => $objReport->mar_submitted_at?->format('M j, Y'),
                ],
                'reviewed_by' => [
                    'name' => $objReport->verifier->usr_name ?? null,
                    'position' => $objReport->verifier?->usr_role?->title() ?? 'Verified by (LGU)',
                    'date' => $objReport->mar_verified_at?->format('M j, Y'),
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
        $intDigitalCount = $objBreakdown->filter(fn ($objReport) => $objReport->mar_submission_source === ReportSubmissionSource::Digital)->count();
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
            ->where('mun_id', $intMunicipalityId)
            ->get()
            ->filter(fn (Listing $objListing) => $objListing->isQrEnabled() && ! $objBreakdown->contains(fn ($objRow) => $objRow->lst_id === $objListing->lst_id))
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
     * @param  Collection<int, array<string, mixed>>  $objRows  Each row must carry
     *                                                          'establishment', 'category', 'male', 'female', 'total', 'adults',
     *                                                          'children', 'seniors', 'local', 'foreign'.
     * @return array<int, string> One message per mismatched row/total — empty
     *                            when every group balances, which is the only time generation may
     *                            proceed.
     */
    public static function validateColumnSums(Collection $objRows): array
    {
        $arrErrors = [];

        foreach ($objRows as $arrRow) {
            $arrErrors = [...$arrErrors, ...self::validateRow($arrRow['establishment'], $arrRow)];
        }

        $arrErrors = [...$arrErrors, ...self::validateRow('Grand Total', self::sumRows($objRows))];

        return $arrErrors;
    }

    /**
     * @param  array<string, mixed>  $arrRow
     * @return array<int, string>
     */
    private static function validateRow(string $strLabel, array $arrRow): array
    {
        $arrErrors = [];

        if ($arrRow['total'] !== $arrRow['male'] + $arrRow['female']) {
            $arrErrors[] = "{$strLabel}: Male + Female (".($arrRow['male'] + $arrRow['female']).") does not equal Total ({$arrRow['total']}).";
        }

        if ($arrRow['total'] !== $arrRow['adults'] + $arrRow['children'] + $arrRow['seniors']) {
            $arrErrors[] = "{$strLabel}: Adults + Children + Seniors (".($arrRow['adults'] + $arrRow['children'] + $arrRow['seniors']).") does not equal Total ({$arrRow['total']}).";
        }

        if ($arrRow['total'] !== $arrRow['local'] + $arrRow['foreign']) {
            $arrErrors[] = "{$strLabel}: Local + Foreign (".($arrRow['local'] + $arrRow['foreign']).") does not equal Total ({$arrRow['total']}).";
        }

        return $arrErrors;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $objRows
     * @return array<string, int>
     */
    public static function sumRows(Collection $objRows): array
    {
        return [
            'male' => (int) $objRows->sum('male'),
            'female' => (int) $objRows->sum('female'),
            'total' => (int) $objRows->sum('total'),
            'adults' => (int) $objRows->sum('adults'),
            'children' => (int) $objRows->sum('children'),
            'seniors' => (int) $objRows->sum('seniors'),
            'local' => (int) $objRows->sum('local'),
            'foreign' => (int) $objRows->sum('foreign'),
        ];
    }

    /**
     * Groups already-row-shaped data by category, each with its own subtotal
     * row, for the "table of establishments grouped by category with
     * subtotals and a grand total" layout.
     *
     * @param  Collection<int, array<string, mixed>>  $objRows
     * @return Collection<string, array{rows: Collection, subtotal: array}>
     */
    public static function groupByCategory(Collection $objRows): Collection
    {
        return $objRows->groupBy('category')->map(fn (Collection $objGroup) => [
            'rows' => $objGroup,
            'subtotal' => self::sumRows($objGroup),
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
