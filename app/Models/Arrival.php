<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visitor-arrival submission — either `staff` (establishment front-desk
 * "Record Arrival" wizard, one visitor per row) or `self_checkin` (public
 * QR self-registration, one party per row). See the create_arrivals_table
 * migration for which columns apply to which source.
 */
#[Fillable([
    'listing_id', 'source', 'date', 'visitor_name', 'visitor_contact',
    'gender', 'classification', 'remarks', 'party_male', 'party_female',
    'party_adults', 'party_children', 'party_seniors', 'party_local',
    'party_foreign', 'party_size', 'status',
])]
class Arrival extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
