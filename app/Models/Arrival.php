<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a visitor-arrival record (staff-logged or QR self-check-in).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use App\Enums\ArrivalOriginScope;
use App\Enums\ArrivalSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visitor-arrival submission, one party per row — either `staff`
 * (establishment front-desk "Record Arrival" wizard, used when a guest
 * can't scan the QR) or `self_checkin` (public QR self-registration). See
 * the create_arrivals_table migration for the legacy single-visitor columns
 * still on this table but no longer written by either form.
 */
#[Fillable([
    'listing_id', 'monthly_arrival_report_id', 'source', 'recorded_by', 'date', 'visitor_name', 'visitor_contact',
    'gender', 'classification', 'visit_type', 'remarks', 'party_male',
    'party_female', 'party_adults', 'party_children', 'party_seniors',
    'party_local', 'party_foreign', 'party_size', 'status',
    'local_origin_scope', 'local_origin_place', 'foreign_country',
])]
class Arrival extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'source' => ArrivalSource::class,
            'local_origin_scope' => ArrivalOriginScope::class,
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * The establishment user who encoded this arrival at the front desk —
     * null for QR self-check-in rows (the tourist has no account).
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    } // end recorder

    /**
     * The digitally-submitted monthly report this row was aggregated into,
     * if its establishment has submitted that period yet.
     */
    public function monthlyArrivalReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyArrivalReport::class);
    }
}
