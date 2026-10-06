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

#[Table('tbl_municipal_reports', key: 'mrp_id')]
#[Fillable([
    'mrp_municipality', 'mrp_submitted_by', 'mrp_period_start', 'mrp_period_end',
    'mrp_total_arrivals', 'mrp_status', 'mrp_reviewed_by', 'mrp_reviewed_at', 'mrp_remarks',
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
        ];
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
}
