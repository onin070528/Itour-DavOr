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
use App\Support\SecurityLogger;
use Database\Factories\UserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

/**
 * `municipality_id`/`establishment_id`/`created_by` stay in #[Fillable] for
 * legitimate admin-initiated create/update calls (Pto\UsersController,
 * Lgu\UsersController), but no controller ever mass-assigns them from raw
 * request input — every write path is an explicit, role-checked field
 * assignment. Self-service settings (Concerns\UpdatesAccountSettings)
 * validate by an allow-list that excludes role/status/municipality_id/
 * establishment_id/created_by entirely, so a user can never change their
 * own scope or forge who created them.
 */
#[Fillable(['name', 'email', 'password', 'role', 'organization_name', 'organization_subtitle', 'status', 'municipality_id', 'establishment_id', 'created_by', 'usr_must_change_password', 'usr_password_changed_at', 'usr_phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * An LGU account's municipality is fixed once assigned — only a
     * signed-in PTO Administrator may move it (Pto\UsersController). A
     * non-PTO actor attempting it gets a 403 no matter which code path
     * tried. Contexts with no signed-in user (seeders, console commands,
     * queued jobs) are left alone so structural backfills such as
     * RbacScopeBackfillSeeder keep working.
     */
    protected static function booted(): void
    {
        static::updating(function (User $objUser) {
            $blnWasOrIsLgu = $objUser->isLgu() || $objUser->getOriginal('role') === UserRole::Lgu;
            $blnIsReassigning = $objUser->isDirty('municipality_id') && $objUser->getOriginal('municipality_id') !== null;

            if (! $blnWasOrIsLgu || ! $blnIsReassigning) {
                return;
            }

            $objActor = Auth::user();

            if ($objActor !== null && ! $objActor->isPto()) {
                throw new AuthorizationException('Only the Provincial Tourism Office can change an LGU account\'s municipality.');
            }
        });

        // Summary comment: an establishment account stays linked to exactly
        // one establishment, in that establishment's municipality. Once
        // linked, only a signed-in PTO Administrator may move it; contexts
        // with no signed-in user (seeders, backfills) are left alone.
        static::updating(function (User $objUser) {
            $blnIsEstablishmentAccount = $objUser->isEstablishment() || $objUser->getOriginal('role') === UserRole::Establishment;
            $blnIsRelinking = ($objUser->isDirty('establishment_id') && $objUser->getOriginal('establishment_id') !== null)
                || ($objUser->isDirty('municipality_id') && $objUser->getOriginal('municipality_id') !== null);

            if (! $blnIsEstablishmentAccount || ! $blnIsRelinking) {
                return;
            }

            $objActor = Auth::user();

            if ($objActor !== null && ! $objActor->isPto()) {
                SecurityLogger::accessDenied($objActor, 'establishment_account_relink', User::class, $objUser->getOriginal('municipality_id'));

                throw new AuthorizationException('Only the Provincial Tourism Office can move an establishment account.');
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'usr_must_change_password' => 'boolean',
            'usr_password_changed_at' => 'datetime',
        ];
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
        return $this->belongsTo(Municipality::class);
    }

    /**
     * The single establishment (Listing) this account is linked to — only
     * ever set for role === Establishment. Establishments and destinations
     * share the `listings` table (see Listing's `category` column).
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'establishment_id');
    }

    /**
     * The account that created this one (PTO creates LGU accounts, LGU
     * creates Establishment accounts). Null for pre-existing seeded rows.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function isPto(): bool
    {
        return $this->role === UserRole::PtoAdministrator;
    }

    public function isLgu(): bool
    {
        return $this->role === UserRole::Lgu;
    }

    public function isEstablishment(): bool
    {
        return $this->role === UserRole::Establishment;
    }

    /**
     * PTO sees every user; LGU sees only Establishment-role users in its
     * own municipality; Establishment sees only itself.
     */
    public function scopeVisibleTo(Builder $query, self $user): Builder
    {
        return match ($user->role) {
            UserRole::PtoAdministrator => $query,
            UserRole::Lgu => $query->where('role', UserRole::Establishment)
                ->where('municipality_id', $user->municipality_id),
            UserRole::Establishment => $query->where('id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
