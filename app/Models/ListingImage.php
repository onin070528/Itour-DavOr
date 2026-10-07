<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a listing's gallery photo (establishment profile images).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('tbl_listing_images', key: 'lsi_id')]
#[Fillable(['lst_id', 'lsi_path', 'lsi_caption', 'lsi_is_primary', 'lsi_sort_order'])]
class ListingImage extends Model
{
    public const CREATED_AT = 'lsi_created_at';

    public const UPDATED_AT = 'lsi_updated_at';

    protected function casts(): array
    {
        return [
            'lsi_is_primary' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'lst_id', 'lst_id');
    }
}
