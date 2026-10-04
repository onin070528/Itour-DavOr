<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for an append-only business/data-action event row.
 * See App\Support\OperationLogger for the write path — rows should not be
 * created directly from controllers.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\AppendOnly;
use Database\Factories\OperationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'user_role', 'action', 'entity_type', 'entity_id', 'municipality_id', 'establishment_id', 'old_values', 'new_values', 'reason', 'ip_address'])]
class OperationLog extends Model
{
    /** @use HasFactory<OperationLogFactory> */
    use AppendOnly, HasFactory;

    public const UPDATED_AT = null;

    /**
     * The closed action vocabulary — the single source of truth for
     * filter-input validation and the Audit Logs page's badge colors.
     */
    public const ACTIONS = [
        'create', 'update', 'delete', 'submit', 'return', 'validate',
        'reject', 'consolidate', 'approve', 'reopen', 'lock', 'unlock', 'export_report',
        'replace', 'purge', 'publish', 'unpublish',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * The Listing row this action concerned, when `entity_type` is an
     * establishment-shaped row. `establishment_id` mirrors the same FK
     * users.establishment_id already points to (the `listings` table).
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'establishment_id');
    }

    /**
     * Badge tone for the Audit Logs page — see resources/views/components/
     * dashboard/status-badge.blade.php for the tone => classes mapping.
     */
    public static function badgeTone(string $action): string
    {
        return match ($action) {
            'approve', 'validate', 'publish' => 'success',
            'return', 'unlock', 'reopen', 'replace', 'unpublish' => 'warning',
            'reject', 'delete', 'purge' => 'danger',
            'create', 'update', 'submit', 'lock', 'export_report' => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * PTO sees every row; LGU sees only its own municipality's; Establishment
     * sees only its own establishment's. Default deny for any other role.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return match ($viewer->role) {
            UserRole::PtoAdministrator => $query,
            UserRole::Lgu => $query->where('municipality_id', $viewer->municipality_id),
            UserRole::Establishment => $query->where('establishment_id', $viewer->establishment_id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
