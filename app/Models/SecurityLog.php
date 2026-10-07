<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for an append-only authentication/account-security
 * event row. See App\Support\SecurityLogger for the write path — rows should
 * not be created directly from controllers.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\AppendOnly;
use Database\Factories\SecurityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('tbl_security_logs', key: 'sec_id')]
#[Fillable(['sec_event_type', 'usr_id', 'sec_attempted_email', 'sec_target_user_id', 'mun_id', 'sec_ip_address', 'sec_user_agent', 'sec_details'])]
class SecurityLog extends Model
{
    /** @use HasFactory<SecurityLogFactory> */
    use AppendOnly, HasFactory;

    public const CREATED_AT = 'sec_created_at';

    public const UPDATED_AT = null;

    /**
     * The closed sec_event_type vocabulary — the single source of truth for
     * filter-input validation and the Audit Logs page's badge colors.
     */
    public const EVENT_TYPES = [
        'login_success', 'login_failed', 'logout', 'password_changed',
        'password_reset_requested', 'password_reset_completed',
        'otp_sent', 'otp_failed', 'otp_verified',
        'account_created', 'account_approved', 'account_suspended', 'account_reactivated',
        'role_changed', 'access_denied',
    ];

    protected function casts(): array
    {
        return [
            'sec_details' => 'array',
        ];
    }

    /**
     * The account that performed the action (null for a failed login on an
     * unknown email — see sec_attempted_email).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usr_id', 'usr_id');
    }

    /**
     * The account that was acted on, e.g. the account an admin suspended.
     * Distinct from `user` (the acting admin) — the two are the same only
     * for self-service events like a user's own login/logout.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sec_target_user_id', 'usr_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'mun_id', 'mun_id');
    }

    /**
     * Badge tone for the Audit Logs page — see resources/views/components/
     * dashboard/status-badge.blade.php for the tone => classes mapping.
     */
    public static function badgeTone(string $strEventType): string
    {
        return match ($strEventType) {
            'login_success', 'otp_verified' => 'success',
            'return', 'unlock', 'password_reset_requested', 'account_suspended' => 'warning',
            'login_failed', 'otp_failed', 'access_denied' => 'danger',
            'account_created', 'password_changed', 'role_changed' => 'info',
            'account_approved' => 'success',
            default => 'neutral',
        };
    }

    /**
     * PTO sees every row. LGU sees only its own security events plus those
     * of establishment_owner accounts in its own municipality — checked on
     * whichever side (user/target_user) of the row an Establishment account
     * would appear on. Establishment sees only its own login history.
     * Default deny for any other role.
     */
    public function scopeVisibleTo(Builder $objQuery, User $objViewer): Builder
    {
        return match ($objViewer->usr_role) {
            UserRole::PtoAdministrator => $objQuery,
            UserRole::Lgu => $objQuery->where(function (Builder $objQuery) use ($objViewer) {
                $objQuery->where('usr_id', $objViewer->usr_id)
                    ->orWhere('sec_target_user_id', $objViewer->usr_id)
                    ->orWhereHas('user', fn (Builder $objQuery2) => $objQuery2->where('usr_role', UserRole::Establishment)->where('mun_id', $objViewer->mun_id))
                    ->orWhereHas('targetUser', fn (Builder $objQuery2) => $objQuery2->where('usr_role', UserRole::Establishment)->where('mun_id', $objViewer->mun_id));
            }),
            UserRole::Establishment => $objQuery->where('usr_id', $objViewer->usr_id),
            default => $objQuery->whereRaw('1 = 0'),
        };
    }
}
