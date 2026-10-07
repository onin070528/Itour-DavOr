<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for tbl_establishment_images, the establishment photo upload/approval
 * workflow.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One establishment photo and its approval history. `lst_id` is the
 * real FK — it references the `tbl_listings` table (App\Models\
 * Listing). `img_replaces_id` chains Replace requests (the new row
 * stays Pending while the row it targets stays Published until approval —
 * see Establishment\ImagesController::replace() in a later sub-stage).
 */
#[Table('tbl_establishment_images', key: 'img_id')]
#[Fillable([
    'lst_id', 'img_path', 'img_thumbnail_path', 'img_alt_text', 'img_credit',
    'img_source_role', 'img_status', 'img_is_cover', 'img_sort_order', 'img_hash',
    'img_review_note', 'img_uploaded_by', 'img_reviewed_by', 'img_reviewed_at',
    'img_replaces_id', 'img_has_ownership_declared', 'img_archived_at',
])]
class EstablishmentImage extends Model
{
    public const CREATED_AT = 'img_created_at';

    public const UPDATED_AT = 'img_updated_at';

    protected function casts(): array
    {
        return [
            'img_source_role' => ImageSourceRole::class,
            'img_status' => ImageStatus::class,
            'img_is_cover' => 'boolean',
            'img_has_ownership_declared' => 'boolean',
            'img_reviewed_at' => 'datetime',
            'img_archived_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'lst_id', 'lst_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'img_uploaded_by', 'usr_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'img_reviewed_by', 'usr_id');
    }

    /**
     * The Published image this row is a pending Replace request for.
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'img_replaces_id', 'img_id');
    }

    /**
     * The pending Replace request against this (Published) image, if any —
     * "Only one pending replacement per image" means this is a HasOne, not
     * a HasMany.
     */
    public function replacedBy(): HasOne
    {
        return $this->hasOne(self::class, 'img_replaces_id', 'img_id');
    }

    /**
     * Whether this image is currently visible on public pages — Published,
     * and only Published; Pending/Returned/Archived never show publicly.
     */
    public function isPublished(): bool
    {
        return $this->img_status === ImageStatus::Published;
    }

    /**
     * Whether this image is still awaiting a decision — used by the batch
     * approval queue to skip any id that was already decided by someone
     * else (or is simply stale) by the time its turn comes in the batch.
     */
    public function isPending(): bool
    {
        return $this->img_status === ImageStatus::Pending;
    }
}
