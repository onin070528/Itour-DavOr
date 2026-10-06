<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single write path for App\Models\SecurityLog rows. Called from
 * app/Listeners (Laravel's own auth events plus this app's account-lifecycle
 * events), not directly from controllers, so every security event is logged
 * exactly once in exactly one place.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Enums\UserRole;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Throwable;

class SecurityLogger
{
    public static function loginSuccess(User $objUser): void
    {
        self::write('login_success', user: $objUser);
    }

    /**
     * @param  string|null  $strReason  Non-sensitive context only, e.g.
     *                                  'invalid_credentials' or
     *                                  'account_suspended' — never a password.
     */
    public static function loginFailed(?User $objUser, ?string $strAttemptedEmail, ?string $strReason = null): void
    {
        self::write('login_failed', user: $objUser, attemptedEmail: $objUser ? null : $strAttemptedEmail, details: $strReason ? ['reason' => $strReason] : []);
    }

    public static function logout(User $objUser): void
    {
        self::write('logout', user: $objUser);
    }

    public static function passwordChanged(User $objUser): void
    {
        self::write('password_changed', user: $objUser);
    }

    public static function passwordResetRequested(User $objUser): void
    {
        self::write('password_reset_requested', user: $objUser);
    }

    public static function passwordResetCompleted(User $objUser): void
    {
        self::write('password_reset_completed', user: $objUser);
    }

    public static function accountCreated(User $objActor, User $objAccount): void
    {
        self::write('account_created', user: $objActor, targetUser: $objAccount);
    }

    /**
     * @param  string  $strNewStatus  'Active' or 'Inactive'.
     */
    public static function accountStatusChanged(User $objActor, User $objAccount, string $strNewStatus): void
    {
        self::write($strNewStatus === 'Inactive' ? 'account_suspended' : 'account_reactivated', user: $objActor, targetUser: $objAccount);
    }

    public static function roleChanged(User $objActor, User $objAccount, UserRole $objFromRole, UserRole $objToRole): void
    {
        self::write('role_changed', user: $objActor, targetUser: $objAccount, details: [
            'from' => $objFromRole->value,
            'to' => $objToRole->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $details  Short, non-sensitive context only.
     */
    private static function write(
        string $eventType,
        ?User $user = null,
        ?string $attemptedEmail = null,
        ?User $targetUser = null,
        array $details = [],
    ): void {
        try {
            SecurityLog::query()->create([
                'sec_event_type' => $eventType,
                'usr_id' => $user?->usr_id,
                'sec_attempted_email' => $attemptedEmail,
                'sec_target_user_id' => $targetUser?->usr_id,
                'mun_id' => $user?->mun_id ?? $targetUser?->mun_id,
                'sec_ip_address' => Request::ip(),
                'sec_user_agent' => Str::limit((string) Request::userAgent(), 255, ''),
                'sec_details' => $details ?: null,
            ]);
        } catch (Throwable $objException) {
            // Never let a logging failure break the action it's logging.
            Log::error('Failed to write security log.', ['event_type' => $eventType, 'exception' => $objException]);
        }
    }
}
