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

#[Fillable([
    'municipality', 'submitted_by', 'period_start', 'period_end',
    'total_arrivals', 'status', 'reviewed_by', 'reviewed_at', 'remarks',
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
        ];
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
}
