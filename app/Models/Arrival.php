<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a visitor-arrival record (staff-logged or QR self-check-in).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('tbl_arrivals', key: 'arr_id')]
#[Fillable([
    'lst_id', 'arr_source', 'arr_date', 'arr_visitor_name', 'arr_visitor_contact',
    'arr_gender', 'arr_classification', 'arr_visit_type', 'arr_remarks', 'arr_party_male',
    'arr_party_female', 'arr_party_adults', 'arr_party_children', 'arr_party_seniors',
    'arr_party_local', 'arr_party_foreign', 'arr_party_size', 'arr_status',
])]
class Arrival extends Model
{
    public const CREATED_AT = 'arr_created_at';

    public const UPDATED_AT = 'arr_updated_at';

    protected function casts(): array
    {
        return [
            'arr_date' => 'date',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'lst_id', 'lst_id');
    }
}
