<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for an authenticated account (PTO/LGU/Establishment roles).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

/**
 * `mun_id`/`lst_id`/`usr_created_by` stay in #[Fillable] for legitimate
 * admin-initiated create/update calls (Pto\UsersController,
 * Lgu\UsersController), but no controller ever mass-assigns them from raw
 * request input — every write path is an explicit, role-checked field
 * assignment. Self-service settings (Concerns\UpdatesAccountSettings)
 * validate by an allow-list that excludes usr_role/usr_status/mun_id/lst_id/
 * usr_created_by entirely, so a user can never change their own scope or
 * forge who created them.
 */
#[Table('tbl_users', key: 'usr_id')]
#[Fillable(['usr_name', 'usr_email', 'usr_password', 'usr_role', 'usr_organization_name', 'usr_organization_subtitle', 'usr_status', 'mun_id', 'lst_id', 'usr_created_by', 'usr_must_change_password', 'usr_password_changed_at', 'usr_phone'])]
#[Hidden(['usr_password', 'usr_remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const CREATED_AT = 'usr_created_at';

    public const UPDATED_AT = 'usr_updated_at';

    /**
     * Column holding the hashed password (read by the auth guard and the
     * `current_password` validation rule).
     */
    protected $authPasswordName = 'usr_password';

    /**
     * Column holding the "remember me" token.
     */
    protected $rememberTokenName = 'usr_remember_token';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usr_email_verified_at' => 'datetime',
            'usr_last_login_at' => 'datetime',
            'usr_password' => 'hashed',
            'usr_role' => UserRole::class,
            'usr_must_change_password' => 'boolean',
            'usr_password_changed_at' => 'datetime',
        ];
    }

    /**
     * The address password reset links are stored against and sent to.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->usr_email;
    }

    /**
     * Mail notifications (e.g. the password reset link) go to usr_email.
     */
    public function routeNotificationForMail(Notification $objNotification): string
    {
        return $this->usr_email;
    }

    /**
     * True for an account that must set its own password before reaching
     * any other page — a brand-new account created with a temporary
     * passphrase (see App\Http\Middleware\ForcePasswordChange). Always
     * false for every account that existed before this feature shipped.
     */
    public function mustChangePassword(): bool
    {
        return (bool) $this->usr_must_change_password;
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'mun_id', 'mun_id');
    }

    /**
     * The single establishment (Listing) this account is linked to — only
     * ever set for role === Establishment. Establishments and destinations
     * share the `tbl_listings` table (see Listing's `lst_category` column).
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'lst_id', 'lst_id');
    }

    /**
     * The account that created this one (PTO creates LGU accounts, LGU
     * creates Establishment accounts). Null for pre-existing seeded rows.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'usr_created_by', 'usr_id');
    }

    public function isPto(): bool
    {
        return $this->usr_role === UserRole::PtoAdministrator;
    }

    public function isLgu(): bool
    {
        return $this->usr_role === UserRole::Lgu;
    }

    public function isEstablishment(): bool
    {
        return $this->usr_role === UserRole::Establishment;
    }

    /**
     * PTO sees every user; LGU sees only Establishment-role users in its
     * own municipality; Establishment sees only itself.
     */
    public function scopeVisibleTo(Builder $objQuery, self $objUser): Builder
    {
        return match ($objUser->usr_role) {
            UserRole::PtoAdministrator => $objQuery,
            UserRole::Lgu => $objQuery->where('usr_role', UserRole::Establishment)
                ->where('mun_id', $objUser->mun_id),
            UserRole::Establishment => $objQuery->where('usr_id', $objUser->usr_id),
            default => $objQuery->whereRaw('1 = 0'),
        };
    }
}
