<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for tbl_announcements, PTO-posted announcements/promotions/advisories.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('tbl_announcements', key: 'ann_id')]
#[Fillable(['ann_title', 'ann_body', 'ann_type', 'ann_start_date', 'ann_end_date', 'ann_is_published', 'ann_created_by', 'ann_updated_by'])]
class Announcement extends Model
{
    public const TYPE_PROMOTION = 'Promotion';

    public const TYPE_ADVISORY = 'Advisory';

    public const TYPE_EVENT = 'Event';

    public const TYPE_ANNOUNCEMENT = 'Announcement';

    public const CREATED_AT = 'ann_created_at';

    public const UPDATED_AT = 'ann_updated_at';

    protected function casts(): array
    {
        return [
            'ann_start_date' => 'date',
            'ann_end_date' => 'date',
            'ann_is_published' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ann_created_by', 'usr_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ann_updated_by', 'usr_id');
    }

    /**
     * Published announcements currently within their display window (or
     * with no window set), newest first, for the public landing page.
     */
    public function scopeCurrentlyVisible(Builder $objQuery): Builder
    {
        $dtmToday = now()->toDateString();

        return $objQuery->where('ann_is_published', true)
            ->where(function (Builder $objQuery) use ($dtmToday) {
                $objQuery->whereNull('ann_start_date')->orWhere('ann_start_date', '<=', $dtmToday);
            })
            ->where(function (Builder $objQuery) use ($dtmToday) {
                $objQuery->whereNull('ann_end_date')->orWhere('ann_end_date', '>=', $dtmToday);
            })
            ->orderByDesc('ann_start_date');
    }
}
