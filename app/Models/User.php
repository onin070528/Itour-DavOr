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

#[Table('tbl_users', key: 'usr_id')]
#[Fillable(['usr_name', 'usr_email', 'usr_password', 'usr_role', 'usr_organization_name', 'usr_organization_subtitle', 'usr_status', 'mun_id', 'lst_id'])]
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
    public function routeNotificationForMail(Notification $notification): string
    {
        return $this->usr_email;
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
     * PTO sees every user; LGU sees only Establishment-role users in its
     * own municipality; Establishment sees only itself.
     */
    public function scopeVisibleTo(Builder $query, self $user): Builder
    {
        return match ($user->usr_role) {
            UserRole::PtoAdministrator => $query,
            UserRole::Lgu => $query->where('usr_role', UserRole::Establishment)
                ->where('mun_id', $user->mun_id),
            UserRole::Establishment => $query->where('usr_id', $user->usr_id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
