<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for tbl_feedback_issues, one recurring issue
 * category detected in a negative tourist feedback row.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Written only by the analysis pipeline through Feedback::issues(), never
 * from request input. Rows are created once and never updated, so there
 * is no updated-at column.
 */
#[Table('tbl_feedback_issues', key: 'fbi_id')]
#[Fillable(['fbi_issue_category', 'fbi_matched_keyword'])]
class FeedbackIssue extends Model
{
    public const CREATED_AT = 'fbi_created_at';

    public const UPDATED_AT = null;

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class, 'fbk_id', 'fbk_id');
    }
}
