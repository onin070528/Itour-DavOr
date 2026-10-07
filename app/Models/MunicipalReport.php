<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a consolidated municipal tourism report
 * submitted by an LGU Tourism Admin and reviewed by the PTO.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('tbl_municipal_reports', key: 'mrp_id')]
#[Fillable([
    'mrp_municipality', 'mun_id', 'mrp_submitted_by', 'mrp_period_start', 'mrp_period_end',
    'mrp_total_arrivals', 'mrp_status', 'mrp_reviewed_by', 'mrp_reviewed_at', 'mrp_remarks',
    'mrp_verification_code', 'mrp_revision_number', 'mrp_supersedes_id', 'mrp_frozen_snapshot',
])]
class MunicipalReport extends Model
{
    public const CREATED_AT = 'mrp_created_at';

    public const UPDATED_AT = 'mrp_updated_at';

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_REVIEWED = 'REVIEWED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_RETURNED = 'RETURNED';

    protected function casts(): array
    {
        return [
            'mrp_period_start' => 'date',
            'mrp_period_end' => 'date',
            'mrp_reviewed_at' => 'datetime',
            'mrp_total_arrivals' => 'integer',
            'mrp_revision_number' => 'integer',
            'mrp_frozen_snapshot' => 'array',
        ];
    }

    /**
     * The Verified report this one reopened and replaced (A3-style audit
     * trail: a resubmission over a Verified report always creates a new
     * row — see Lgu\MonthlyReportsController::consolidate() — never
     * overwrites it, so a Verified report's own row is permanently frozen).
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mrp_supersedes_id', 'mrp_id');
    }

    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'mrp_supersedes_id', 'mrp_id');
    }

    public function isFrozen(): bool
    {
        return $this->mrp_status === self::STATUS_APPROVED;
    }

    /**
     * The LGU Tourism Admin who submitted this report.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mrp_submitted_by', 'usr_id');
    }

    /**
     * The PTO Administrator who reviewed this report, if any.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mrp_reviewed_by', 'usr_id');
    }

    public function municipalityRecord(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'mun_id', 'mun_id');
    }

    /**
     * The verified establishment-month reports consolidated into this one
     * by Lgu\MonthlyReportsController::consolidate().
     */
    public function monthlyArrivalReports(): HasMany
    {
        return $this->hasMany(MonthlyArrivalReport::class, 'mrp_id', 'mrp_id');
    }
}
