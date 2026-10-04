<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Eloquent model for tblannouncements, PTO-posted announcements/promotions/advisories.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ann_title', 'ann_body', 'ann_type', 'ann_start_date', 'ann_end_date', 'ann_is_published', 'ann_created_by', 'ann_updated_by'])]
class Announcement extends Model
{
    public const TYPE_PROMOTION = 'Promotion';

    public const TYPE_ADVISORY = 'Advisory';

    public const TYPE_EVENT = 'Event';

    public const TYPE_ANNOUNCEMENT = 'Announcement';

    public const CREATED_AT = 'ann_created_at';

    public const UPDATED_AT = 'ann_updated_at';

    protected $table = 'tblannouncements';

    protected $primaryKey = 'ann_id';

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
        return $this->belongsTo(User::class, 'ann_created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ann_updated_by');
    }

    /**
     * Published announcements currently within their display window (or
     * with no window set), newest first, for the public landing page.
     */
    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        $dtmToday = now()->toDateString();

        return $query->where('ann_is_published', true)
            ->where(function (Builder $query) use ($dtmToday) {
                $query->whereNull('ann_start_date')->orWhere('ann_start_date', '<=', $dtmToday);
            })
            ->where(function (Builder $query) use ($dtmToday) {
                $query->whereNull('ann_end_date')->orWhere('ann_end_date', '>=', $dtmToday);
            })
            ->orderByDesc('ann_start_date');
    }
}
