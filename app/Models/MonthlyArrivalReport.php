<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for one establishment's consolidated tourist-
 * arrival report for a single calendar month — the shared unit both the
 * Digital and Manual/Paper submission paths write into.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use App\Enums\ArrivalOriginScope;
use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One row per (lst_id, mar_period_month). `mar_submission_source` distinguishes
 * how it arrived — Digital (system-aggregated from that period's Arrival
 * rows at establishment submit time) or ManualPaper (LGU-encoded totals from
 * a physical report) — but both populate this same table, so LGU review,
 * verification, and consolidation into MunicipalReport never branch on
 * source. See App\Enums\MonthlyReportStatus for why "Not Submitted" is never
 * a value stored here.
 */
#[Table('tbl_monthly_arrival_reports', key: 'mar_id')]
#[Fillable([
    'lst_id', 'mun_id', 'mar_period_month', 'mar_submission_source', 'mar_status',
    'mar_party_male', 'mar_party_female', 'mar_party_adults', 'mar_party_children', 'mar_party_seniors',
    'mar_party_local', 'mar_party_foreign', 'mar_total_visitors',
    'mar_submitted_by', 'mar_submitted_at', 'mar_verified_by', 'mar_verified_at',
    'mrp_id', 'mar_remarks',
])]
class MonthlyArrivalReport extends Model
{
    use HasFactory;

    public const CREATED_AT = 'mar_created_at';

    public const UPDATED_AT = 'mar_updated_at';

    protected function casts(): array
    {
        return [
            'mar_period_month' => 'date',
            'mar_submission_source' => ReportSubmissionSource::class,
            'mar_status' => MonthlyReportStatus::class,
            'mar_submitted_at' => 'datetime',
            'mar_verified_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'lst_id', 'lst_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'mun_id', 'mun_id');
    }

    /**
     * The user who submitted this report — the establishment user for
     * Digital, or the LGU user who encoded it for ManualPaper.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mar_submitted_by', 'usr_id');
    }

    /**
     * The LGU user who marked this report Verified, if any.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mar_verified_by', 'usr_id');
    }

    public function municipalReport(): BelongsTo
    {
        return $this->belongsTo(MunicipalReport::class, 'mrp_id', 'mrp_id');
    }

    /**
     * The underlying per-visitor Arrival rows this report was aggregated
     * from — only ever populated for mar_submission_source = Digital.
     */
    public function arrivals(): HasMany
    {
        return $this->hasMany(Arrival::class, 'mar_id', 'mar_id');
    }

    /**
     * Within/outside-province and top-origin breakdown of this report's
     * underlying arrivals (see arrivals(), above — only ever populated for
     * Digital reports, so a ManualPaper report simply yields all-empty
     * results here rather than an error). Headcounts are weighted by
     * arr_party_local/arr_party_foreign, not arr_party_size, since a single arrival
     * row's arr_local_origin_place/arr_foreign_country describes only the local or
     * foreign portion of that party, not everyone in it.
     *
     * @return array{withinProvince: int, outsideProvince: int, topOriginPlaces: Collection<string, int>, topForeignCountries: Collection<string, int>}
     */
    public function originBreakdown(): array
    {
        $objArrivals = $this->relationLoaded('arrivals') ? $this->arrivals : $this->arrivals()->get();

        return [
            'withinProvince' => (int) $objArrivals->where('arr_local_origin_scope', ArrivalOriginScope::WithinProvince)->sum('arr_party_local'),
            'outsideProvince' => (int) $objArrivals->where('arr_local_origin_scope', ArrivalOriginScope::OutsideProvince)->sum('arr_party_local'),
            'topOriginPlaces' => $objArrivals->whereNotNull('arr_local_origin_place')
                ->groupBy('arr_local_origin_place')
                ->map(fn ($objGroup) => $objGroup->sum('arr_party_local'))
                ->sortDesc()
                ->take(5),
            'topForeignCountries' => $objArrivals->whereNotNull('arr_foreign_country')
                ->groupBy('arr_foreign_country')
                ->map(fn ($objGroup) => $objGroup->sum('arr_party_foreign'))
                ->sortDesc()
                ->take(5),
        ];
    }

    public function scopeVisibleTo(Builder $objQuery, User $objUser): Builder
    {
        return match ($objUser->usr_role) {
            UserRole::PtoAdministrator => $objQuery,
            UserRole::Lgu => $objQuery->where('mun_id', $objUser->mun_id),
            UserRole::Establishment => $objQuery->where('lst_id', $objUser->lst_id),
            default => $objQuery->whereRaw('1 = 0'),
        };
    }

    public function scopeForPeriod(Builder $objQuery, CarbonInterface $dtmMonth): Builder
    {
        // whereDate(), not where() — Eloquent's date cast serializes this
        // column with a time component on write (connection grammar's
        // dateFormat, not date-only), so an exact string match against
        // toDateString() alone would silently never find existing rows.
        return $objQuery->whereDate('mar_period_month', $dtmMonth->copy()->startOfMonth()->toDateString());
    }
}
