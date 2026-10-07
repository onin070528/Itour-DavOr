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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One row per (listing_id, period_month). `submission_source` distinguishes
 * how it arrived — Digital (system-aggregated from that period's Arrival
 * rows at establishment submit time) or ManualPaper (LGU-encoded totals from
 * a physical report) — but both populate this same table, so LGU review,
 * verification, and consolidation into MunicipalReport never branch on
 * source. See App\Enums\MonthlyReportStatus for why "Not Submitted" is never
 * a value stored here.
 */
#[Fillable([
    'listing_id', 'municipality_id', 'period_month', 'submission_source', 'status',
    'party_male', 'party_female', 'party_adults', 'party_children', 'party_seniors',
    'party_local', 'party_foreign', 'total_visitors',
    'submitted_by', 'submitted_at', 'verified_by', 'verified_at',
    'municipal_report_id', 'remarks',
])]
class MonthlyArrivalReport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'submission_source' => ReportSubmissionSource::class,
            'status' => MonthlyReportStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * The user who submitted this report — the establishment user for
     * Digital, or the LGU user who encoded it for ManualPaper. Null while
     * the report is still a Draft.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * The LGU user who marked this report Verified, if any.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function municipalReport(): BelongsTo
    {
        return $this->belongsTo(MunicipalReport::class);
    }

    /**
     * The underlying per-visitor Arrival rows this report was aggregated
     * from — only ever populated for submission_source = Digital.
     */
    public function arrivals(): HasMany
    {
        return $this->hasMany(Arrival::class);
    }

    /**
     * Within/outside-province and top-origin breakdown of this report's
     * underlying arrivals (see arrivals(), above — only ever populated for
     * Digital reports, so a ManualPaper report simply yields all-empty
     * results here rather than an error). Headcounts are weighted by
     * party_local/party_foreign, not party_size, since a single arrival
     * row's local_origin_place/foreign_country describes only the local or
     * foreign portion of that party, not everyone in it.
     *
     * @return array{withinProvince: int, outsideProvince: int, topOriginPlaces: Collection<string, int>, topForeignCountries: Collection<string, int>}
     */
    public function originBreakdown(): array
    {
        $arrivals = $this->relationLoaded('arrivals') ? $this->arrivals : $this->arrivals()->get();

        return [
            'withinProvince' => (int) $arrivals->where('local_origin_scope', ArrivalOriginScope::WithinProvince)->sum('party_local'),
            'outsideProvince' => (int) $arrivals->where('local_origin_scope', ArrivalOriginScope::OutsideProvince)->sum('party_local'),
            // Provinces only: a within-province row's local_origin_place is
            // a Davao Oriental municipality, not a province, and must never
            // show up in the "Top Origin Provinces" line of the reports.
            'topOriginPlaces' => $arrivals->where('local_origin_scope', ArrivalOriginScope::OutsideProvince)
                ->whereNotNull('local_origin_place')
                ->groupBy('local_origin_place')
                ->map(fn ($group) => $group->sum('party_local'))
                ->sortDesc()
                ->take(5),
            'topForeignCountries' => $arrivals->whereNotNull('foreign_country')
                ->groupBy('foreign_country')
                ->map(fn ($group) => $group->sum('party_foreign'))
                ->sortDesc()
                ->take(5),
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::PtoAdministrator => $query,
            UserRole::Lgu => $query->where('municipality_id', $user->municipality_id),
            UserRole::Establishment => $query->where('listing_id', $user->establishment_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function scopeForPeriod(Builder $query, CarbonInterface $month): Builder
    {
        // whereDate(), not where() — Eloquent's date cast serializes this
        // column with a time component on write (connection grammar's
        // dateFormat, not date-only), so an exact string match against
        // toDateString() alone would silently never find existing rows.
        return $query->whereDate('period_month', $month->copy()->startOfMonth()->toDateString());
    }
}
