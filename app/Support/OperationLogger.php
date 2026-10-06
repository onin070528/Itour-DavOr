<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single write path for App\Models\OperationLog rows — business/data
 * actions on establishments, destinations, and municipal reports. Called
 * from the controllers that perform those actions (there is no Laravel
 * built-in event for "a destination was edited"), never by building an
 * OperationLog row directly.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class OperationLogger
{
    /**
     * @param  array<string, mixed>  $arrNewValues  Safe, already-masked field values — see diff().
     */
    public static function created(User $objUser, string $strEntityType, int $intEntityId, ?int $intMunicipalityId, ?int $intEstablishmentId, array $arrNewValues): void
    {
        self::write('create', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, $intEstablishmentId, null, $arrNewValues ?: null, null);
    }

    /**
     * $strReason is optional — most updates (e.g. editing a destination) don't
     * need one, but a correction to an already-submitted/verified report
     * does, so callers that have one (LGU correcting a MonthlyArrivalReport
     * after PTO returns it for clarification) can pass it through rather
     * than losing it.
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $arrDiff  From diff() — null if nothing meaningful changed.
     */
    public static function updated(User $objUser, string $strEntityType, int $intEntityId, ?int $intMunicipalityId, ?int $intEstablishmentId, ?array $arrDiff, ?string $strReason = null): void
    {
        self::write('update', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, $intEstablishmentId, $arrDiff['old'] ?? null, $arrDiff['new'] ?? null, $strReason);
    }

    /**
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $arrDiff
     */
    public static function validated(User $objUser, string $strEntityType, int $intEntityId, ?int $intMunicipalityId, ?int $intEstablishmentId, ?array $arrDiff = null): void
    {
        self::write('validate', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, $intEstablishmentId, $arrDiff['old'] ?? null, $arrDiff['new'] ?? null, null);
    }

    /**
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $arrDiff
     */
    public static function approved(User $objUser, string $strEntityType, int $intEntityId, ?int $intMunicipalityId, ?array $arrDiff = null): void
    {
        self::write('approve', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, null, $arrDiff['old'] ?? null, $arrDiff['new'] ?? null, null);
    }

    /**
     * $strReason is required (not nullable) — return/reject/unlock/delete must
     * always carry one, per the logging rules this service enforces.
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $arrDiff
     */
    /**
     * $intEstablishmentId is null for a photo return (entity_type
     * 'establishment_image' — the establishment it belongs to isn't this
     * row's own id); App\Services\ListingPublishWorkflow's listing-level
     * returns (entity_type 'establishment') pass the listing's own id, same
     * convention as validated()/created().
     */
    public static function returned(User $objUser, string $strEntityType, int $intEntityId, string $strReason, ?int $intMunicipalityId, ?array $arrDiff = null, ?int $intEstablishmentId = null): void
    {
        self::write('return', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, $intEstablishmentId, $arrDiff['old'] ?? null, $arrDiff['new'] ?? null, $strReason);
    }

    /**
     * An LGU submitting a listing to PTO for review (DRAFT/UNPUBLISHED →
     * FOR_PTO_REVIEW) — App\Services\ListingPublishWorkflow::submitToPto().
     * $intEntityId IS the establishment's own id (entity_type is always
     * 'establishment' here), same convention as validated()/created().
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $arrDiff
     */
    public static function submitted(User $objUser, string $strEntityType, int $intEntityId, ?int $intMunicipalityId, ?array $arrDiff = null): void
    {
        self::write('submit', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, $intEntityId, $arrDiff['old'] ?? null, $arrDiff['new'] ?? null, null);
    }

    /**
     * PTO publishing a listing (FOR_PTO_REVIEW → PUBLISHED) —
     * App\Services\ListingPublishWorkflow::publish(). $strReason is null for a
     * first publish; unpublish() reuses this with a required reason instead
     * (see App\Services\ListingPublishWorkflow::unpublish()).
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $arrDiff
     */
    public static function published(User $objUser, string $strEntityType, int $intEntityId, ?int $intMunicipalityId, ?array $arrDiff = null, ?string $strReason = null): void
    {
        self::write('publish', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, $intEntityId, $arrDiff['old'] ?? null, $arrDiff['new'] ?? null, $strReason);
    }

    /**
     * PTO unpublishing an already-live listing (PUBLISHED → UNPUBLISHED) —
     * App\Services\ListingPublishWorkflow::unpublish(). $strReason is required,
     * same as returned().
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $arrDiff
     */
    public static function unpublished(User $objUser, string $strEntityType, int $intEntityId, string $strReason, ?int $intMunicipalityId, ?array $arrDiff = null): void
    {
        self::write('unpublish', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, $intEntityId, $arrDiff['old'] ?? null, $arrDiff['new'] ?? null, $strReason);
    }

    /**
     * An LGU rolling up its municipality's Verified monthly reports into a
     * single MunicipalReport and submitting it to PTO — one user action,
     * one log entry, even though it both creates the MunicipalReport row
     * and is "submit to PTO" (there's no separate submission step).
     *
     * @param  array<string, mixed>  $arrNewValues
     */
    public static function consolidated(User $objUser, string $strEntityType, int $intEntityId, ?int $intMunicipalityId, array $arrNewValues): void
    {
        self::write('consolidate', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, null, null, $arrNewValues ?: null, null);
    }

    /**
     * A Replace request — a new, still-Pending row pointed at the Published
     * image it would take over from (img_replaces_id). Kept distinct from
     * created() so the audit trail reads as "replacement requested," not an
     * ordinary first upload.
     *
     * @param  array<string, mixed>  $arrNewValues
     */
    public static function replaced(User $objUser, string $strEntityType, int $intEntityId, ?int $intMunicipalityId, ?int $intEstablishmentId, array $arrNewValues): void
    {
        self::write('replace', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, $intEstablishmentId, null, $arrNewValues ?: null, null);
    }

    /**
     * The scheduled purge job clearing an Archived image's file paths once
     * past the retention period (I3) — the database row itself is kept.
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $arrDiff
     */
    public static function purged(string $strEntityType, int $intEntityId, ?array $arrDiff = null): void
    {
        try {
            OperationLog::query()->create([
                'usr_id' => null,
                'opl_user_role' => null,
                'opl_action' => 'purge',
                'opl_entity_type' => $strEntityType,
                'opl_entity_id' => $intEntityId,
                'opl_old_values' => $arrDiff['old'] ?? null,
                'opl_new_values' => $arrDiff['new'] ?? null,
                'opl_ip_address' => null,
            ]);
        } catch (Throwable $objException) {
            Log::error('Failed to write operation log.', ['action' => 'purge', 'entity_type' => $strEntityType, 'exception' => $objException]);
        }
    }

    /**
     * An LGU resubmitting over an already-Verified (APPROVED) municipal
     * report — the only way such a report can become editable again. Kept
     * distinct from consolidated() so the audit trail reads as "reopened a
     * verified report," not an ordinary first-time submission.
     *
     * @param  array<string, mixed>  $arrNewValues
     */
    public static function reopened(User $objUser, string $strEntityType, int $intEntityId, ?int $intMunicipalityId, array $arrNewValues): void
    {
        self::write('reopen', $objUser, $strEntityType, $intEntityId, $intMunicipalityId, null, null, $arrNewValues ?: null, null);
    }

    /**
     * A CSV/report export — not about one specific record, so entity_id is
     * always null. $arrDetails (row count, active filters) goes in new_values.
     *
     * @param  array<string, mixed>  $arrDetails
     */
    public static function exported(User $objUser, string $strEntityType, ?int $intMunicipalityId, array $arrDetails): void
    {
        self::write('export_report', $objUser, $strEntityType, null, $intMunicipalityId, null, null, $arrDetails ?: null, null);
    }

    /**
     * Diffs $objModel's actually-changed attributes (Eloquent's dirty-tracking,
     * via getChanges() — correct even after ->update() returns) against
     * $arrBefore, a plain snapshot the caller must take BEFORE calling
     * ->update() (e.g. `$arrBefore = $objModel->getOriginal();`). $objModel's own
     * getOriginal() can't be used for the "old" side here: save() calls
     * syncOriginal() as part of finishing, so by the time ->update() has
     * returned, getOriginal() already reflects the NEW values too.
     * Created/updated timestamps are excluded and email/phone-shaped values are masked.
     * Returns null when nothing meaningful changed.
     *
     * @param  array<string, mixed>  $arrBefore
     * @return ?array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public static function diff(array $arrBefore, Model $objModel): ?array
    {
        $objChanged = collect($objModel->getChanges())->reject(fn (mixed $value, string $strKey) => str_ends_with($strKey, '_created_at') || str_ends_with($strKey, '_updated_at'));

        if ($objChanged->isEmpty()) {
            return null;
        }

        $arrOld = [];
        $arrNew = [];

        foreach ($objChanged as $key => $value) {
            $arrOld[$key] = self::mask($key, $arrBefore[$key] ?? null);
            $arrNew[$key] = self::mask($key, $value);
        }

        return ['old' => $arrOld, 'new' => $arrNew];
    }

    private static function mask(string $strKey, mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (str_contains($strKey, 'email')) {
            return preg_replace('/^(.).*(@.+)$/', '$1***$2', $value) ?? $value;
        }

        if (str_contains($strKey, 'phone')) {
            return preg_replace('/^.*(.{4})$/', '***$1', $value) ?? $value;
        }

        return $value;
    }

    /**
     * @param  ?array<string, mixed>  $arrOldValues
     * @param  ?array<string, mixed>  $arrNewValues
     */
    private static function write(
        string $strAction,
        User $objUser,
        string $strEntityType,
        ?int $intEntityId,
        ?int $intMunicipalityId,
        ?int $intEstablishmentId,
        ?array $arrOldValues,
        ?array $arrNewValues,
        ?string $strReason,
    ): void {
        try {
            OperationLog::query()->create([
                'usr_id' => $objUser->usr_id,
                'opl_user_role' => $objUser->usr_role?->value,
                'opl_action' => $strAction,
                'opl_entity_type' => $strEntityType,
                'opl_entity_id' => $intEntityId,
                'mun_id' => $intMunicipalityId,
                'lst_id' => $intEstablishmentId,
                'opl_old_values' => $arrOldValues ?: null,
                'opl_new_values' => $arrNewValues ?: null,
                'opl_reason' => $strReason,
                'opl_ip_address' => Request::ip(),
            ]);
        } catch (Throwable $objException) {
            // Never let a logging failure break the action it's logging.
            Log::error('Failed to write operation log.', ['action' => $strAction, 'entity_type' => $strEntityType, 'exception' => $objException]);
        }
    }
}
