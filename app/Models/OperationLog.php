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
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('tbl_operation_logs', key: 'opl_id')]
#[Fillable(['usr_id', 'opl_user_role', 'opl_action', 'opl_entity_type', 'opl_entity_id', 'mun_id', 'lst_id', 'opl_old_values', 'opl_new_values', 'opl_reason', 'opl_ip_address'])]
class OperationLog extends Model
{
    /** @use HasFactory<OperationLogFactory> */
    use AppendOnly, HasFactory;

    public const CREATED_AT = 'opl_created_at';

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
            'opl_old_values' => 'array',
            'opl_new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usr_id', 'usr_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'mun_id', 'mun_id');
    }

    /**
     * The Listing row this action concerned, when `opl_entity_type` is an
     * establishment-shaped row. `lst_id` mirrors the same FK
     * tbl_users.lst_id already points to (the `tbl_listings` table).
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'lst_id', 'lst_id');
    }

    /**
     * Badge tone for the Audit Logs page — see resources/views/components/
     * dashboard/status-badge.blade.php for the tone => classes mapping.
     */
    public static function badgeTone(string $strAction): string
    {
        return match ($strAction) {
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
    public function scopeVisibleTo(Builder $objQuery, User $objViewer): Builder
    {
        return match ($objViewer->usr_role) {
            UserRole::PtoAdministrator => $objQuery,
            UserRole::Lgu => $objQuery->where('mun_id', $objViewer->mun_id),
            UserRole::Establishment => $objQuery->where('lst_id', $objViewer->lst_id),
            default => $objQuery->whereRaw('1 = 0'),
        };
    }
}
