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
    public static function loginSuccess(User $user): void
    {
        self::write('login_success', user: $user);
    }

    /**
     * @param  string|null  $reason  Non-sensitive context only, e.g.
     *                               'invalid_credentials' or
     *                               'account_suspended' — never a password.
     */
    public static function loginFailed(?User $user, ?string $attemptedEmail, ?string $reason = null): void
    {
        self::write('login_failed', user: $user, attemptedEmail: $user ? null : $attemptedEmail, details: $reason ? ['reason' => $reason] : []);
    }

    public static function logout(User $user): void
    {
        self::write('logout', user: $user);
    }

    public static function passwordChanged(User $user): void
    {
        self::write('password_changed', user: $user);
    }

    public static function passwordResetRequested(User $user): void
    {
        self::write('password_reset_requested', user: $user);
    }

    public static function passwordResetCompleted(User $user): void
    {
        self::write('password_reset_completed', user: $user);
    }

    public static function accountCreated(User $actor, User $account): void
    {
        self::write('account_created', user: $actor, targetUser: $account);
    }

    /**
     * @param  string  $newStatus  'Active' or 'Inactive'.
     */
    public static function accountStatusChanged(User $actor, User $account, string $newStatus): void
    {
        self::write($newStatus === 'Inactive' ? 'account_suspended' : 'account_reactivated', user: $actor, targetUser: $account);
    }

    /**
     * Records a denied authorization attempt without recording request
     * payloads or other sensitive data.
     */
    public static function accessDenied(User $user, string $ability, ?string $resource = null, ?int $targetMunicipalityId = null): void
    {
        self::write('access_denied', user: $user, details: array_filter([
            'ability' => $ability,
            'resource' => $resource,
            'target_municipality_id' => $targetMunicipalityId,
        ], static fn (mixed $value): bool => $value !== null));
    }

    public static function roleChanged(User $actor, User $account, UserRole $fromRole, UserRole $toRole): void
    {
        self::write('role_changed', user: $actor, targetUser: $account, details: [
            'from' => $fromRole->value,
            'to' => $toRole->value,
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
                'event_type' => $eventType,
                'user_id' => $user?->id,
                'attempted_email' => $attemptedEmail,
                'target_user_id' => $targetUser?->id,
                'municipality_id' => $user?->municipality_id ?? $targetUser?->municipality_id,
                'ip_address' => Request::ip(),
                'user_agent' => Str::limit((string) Request::userAgent(), 255, ''),
                'details' => $details ?: null,
            ]);
        } catch (Throwable $e) {
            // Never let a logging failure break the action it's logging.
            Log::error('Failed to write security log.', ['event_type' => $eventType, 'exception' => $e]);
        }
    }
}
