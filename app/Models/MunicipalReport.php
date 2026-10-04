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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'municipality', 'municipality_id', 'submitted_by', 'period_start', 'period_end',
    'total_arrivals', 'status', 'reviewed_by', 'reviewed_at', 'remarks',
    'verification_code', 'revision_number', 'supersedes_id', 'frozen_snapshot',
])]
class MunicipalReport extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_REVIEWED = 'REVIEWED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_RETURNED = 'RETURNED';

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'reviewed_at' => 'datetime',
            'total_arrivals' => 'integer',
            'revision_number' => 'integer',
            'frozen_snapshot' => 'array',
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
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * The LGU Tourism Admin who submitted this report.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * The PTO Administrator who reviewed this report, if any.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function municipalityRecord(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }

    /**
     * The verified establishment-month reports consolidated into this one
     * by Lgu\MonthlyReportsController::consolidate().
     */
    public function monthlyArrivalReports(): HasMany
    {
        return $this->hasMany(MonthlyArrivalReport::class);
    }
}
