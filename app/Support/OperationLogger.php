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
     * @param  array<string, mixed>  $newValues  Safe, already-masked field values — see diff().
     */
    public static function created(User $user, string $entityType, int $entityId, ?int $municipalityId, ?int $establishmentId, array $newValues): void
    {
        self::write('create', $user, $entityType, $entityId, $municipalityId, $establishmentId, null, $newValues ?: null, null);
    }

    /**
     * $reason is optional — most updates (e.g. editing a destination) don't
     * need one, but a correction to an already-submitted/verified report
     * does, so callers that have one (LGU correcting a MonthlyArrivalReport
     * after PTO returns it for clarification) can pass it through rather
     * than losing it.
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $diff  From diff() — null if nothing meaningful changed.
     */
    public static function updated(User $user, string $entityType, int $entityId, ?int $municipalityId, ?int $establishmentId, ?array $diff, ?string $reason = null): void
    {
        self::write('update', $user, $entityType, $entityId, $municipalityId, $establishmentId, $diff['old'] ?? null, $diff['new'] ?? null, $reason);
    }

    /**
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $diff
     */
    public static function validated(User $user, string $entityType, int $entityId, ?int $municipalityId, ?int $establishmentId, ?array $diff = null): void
    {
        self::write('validate', $user, $entityType, $entityId, $municipalityId, $establishmentId, $diff['old'] ?? null, $diff['new'] ?? null, null);
    }

    /**
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $diff
     */
    public static function approved(User $user, string $entityType, int $entityId, ?int $municipalityId, ?array $diff = null): void
    {
        self::write('approve', $user, $entityType, $entityId, $municipalityId, null, $diff['old'] ?? null, $diff['new'] ?? null, null);
    }

    /**
     * $reason is required (not nullable) — return/reject/unlock/delete must
     * always carry one, per the logging rules this service enforces.
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $diff
     */
    /**
     * $establishmentId is null for a photo return (entity_type
     * 'establishment_image' — the establishment it belongs to isn't this
     * row's own id); App\Services\ListingPublishWorkflow's listing-level
     * returns (entity_type 'establishment') pass the listing's own id, same
     * convention as validated()/created().
     */
    public static function returned(User $user, string $entityType, int $entityId, string $reason, ?int $municipalityId, ?array $diff = null, ?int $establishmentId = null): void
    {
        self::write('return', $user, $entityType, $entityId, $municipalityId, $establishmentId, $diff['old'] ?? null, $diff['new'] ?? null, $reason);
    }

    /**
     * An LGU submitting a listing to PTO for review (DRAFT/UNPUBLISHED →
     * FOR_PTO_REVIEW) — App\Services\ListingPublishWorkflow::submitToPto().
     * $entityId IS the establishment's own id (entity_type is always
     * 'establishment' here), same convention as validated()/created().
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $diff
     */
    public static function submitted(User $user, string $entityType, int $entityId, ?int $municipalityId, ?array $diff = null): void
    {
        self::write('submit', $user, $entityType, $entityId, $municipalityId, $entityId, $diff['old'] ?? null, $diff['new'] ?? null, null);
    }

    /**
     * PTO publishing a listing (FOR_PTO_REVIEW → PUBLISHED) —
     * App\Services\ListingPublishWorkflow::publish(). $reason is null for a
     * first publish; unpublish() reuses this with a required reason instead
     * (see App\Services\ListingPublishWorkflow::unpublish()).
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $diff
     */
    public static function published(User $user, string $entityType, int $entityId, ?int $municipalityId, ?array $diff = null, ?string $reason = null): void
    {
        self::write('publish', $user, $entityType, $entityId, $municipalityId, $entityId, $diff['old'] ?? null, $diff['new'] ?? null, $reason);
    }

    /**
     * PTO unpublishing an already-live listing (PUBLISHED → UNPUBLISHED) —
     * App\Services\ListingPublishWorkflow::unpublish(). $reason is required,
     * same as returned().
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $diff
     */
    public static function unpublished(User $user, string $entityType, int $entityId, string $reason, ?int $municipalityId, ?array $diff = null): void
    {
        self::write('unpublish', $user, $entityType, $entityId, $municipalityId, $entityId, $diff['old'] ?? null, $diff['new'] ?? null, $reason);
    }

    /**
     * An LGU rolling up its municipality's Verified monthly reports into a
     * single MunicipalReport and submitting it to PTO — one user action,
     * one log entry, even though it both creates the MunicipalReport row
     * and is "submit to PTO" (there's no separate submission step).
     *
     * @param  array<string, mixed>  $newValues
     */
    public static function consolidated(User $user, string $entityType, int $entityId, ?int $municipalityId, array $newValues): void
    {
        self::write('consolidate', $user, $entityType, $entityId, $municipalityId, null, null, $newValues ?: null, null);
    }

    /**
     * A Replace request — a new, still-Pending row pointed at the Published
     * image it would take over from (img_replaces_id). Kept distinct from
     * created() so the audit trail reads as "replacement requested," not an
     * ordinary first upload.
     *
     * @param  array<string, mixed>  $newValues
     */
    public static function replaced(User $user, string $entityType, int $entityId, ?int $municipalityId, ?int $establishmentId, array $newValues): void
    {
        self::write('replace', $user, $entityType, $entityId, $municipalityId, $establishmentId, null, $newValues ?: null, null);
    }

    /**
     * The scheduled purge job clearing an Archived image's file paths once
     * past the retention period (I3) — the database row itself is kept.
     *
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $diff
     */
    public static function purged(string $entityType, int $entityId, ?array $diff = null): void
    {
        try {
            OperationLog::query()->create([
                'user_id' => null,
                'user_role' => null,
                'action' => 'purge',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $diff['old'] ?? null,
                'new_values' => $diff['new'] ?? null,
                'ip_address' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to write operation log.', ['action' => 'purge', 'entity_type' => $entityType, 'exception' => $e]);
        }
    }

    /**
     * An LGU resubmitting over an already-Verified (APPROVED) municipal
     * report — the only way such a report can become editable again. Kept
     * distinct from consolidated() so the audit trail reads as "reopened a
     * verified report," not an ordinary first-time submission.
     *
     * @param  array<string, mixed>  $newValues
     */
    public static function reopened(User $user, string $entityType, int $entityId, ?int $municipalityId, array $newValues): void
    {
        self::write('reopen', $user, $entityType, $entityId, $municipalityId, null, null, $newValues ?: null, null);
    }

    /**
     * A CSV/report export — not about one specific record, so entity_id is
     * always null. $details (row count, active filters) goes in new_values.
     *
     * @param  array<string, mixed>  $details
     */
    public static function exported(User $user, string $entityType, ?int $municipalityId, array $details): void
    {
        self::write('export_report', $user, $entityType, null, $municipalityId, null, null, $details ?: null, null);
    }

    /**
     * Diffs $model's actually-changed attributes (Eloquent's dirty-tracking,
     * via getChanges() — correct even after ->update() returns) against
     * $before, a plain snapshot the caller must take BEFORE calling
     * ->update() (e.g. `$before = $model->getOriginal();`). $model's own
     * getOriginal() can't be used for the "old" side here: save() calls
     * syncOriginal() as part of finishing, so by the time ->update() has
     * returned, getOriginal() already reflects the NEW values too.
     * Timestamps are excluded and email/phone-shaped values are masked.
     * Returns null when nothing meaningful changed.
     *
     * @param  array<string, mixed>  $before
     * @return ?array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public static function diff(array $before, Model $model): ?array
    {
        $changed = collect($model->getChanges())->except(['created_at', 'updated_at']);

        if ($changed->isEmpty()) {
            return null;
        }

        $old = [];
        $new = [];

        foreach ($changed as $key => $value) {
            $old[$key] = self::mask($key, $before[$key] ?? null);
            $new[$key] = self::mask($key, $value);
        }

        return ['old' => $old, 'new' => $new];
    }

    private static function mask(string $key, mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (str_contains($key, 'email')) {
            return preg_replace('/^(.).*(@.+)$/', '$1***$2', $value) ?? $value;
        }

        if (str_contains($key, 'phone')) {
            return preg_replace('/^.*(.{4})$/', '***$1', $value) ?? $value;
        }

        return $value;
    }

    /**
     * @param  ?array<string, mixed>  $oldValues
     * @param  ?array<string, mixed>  $newValues
     */
    private static function write(
        string $action,
        User $user,
        string $entityType,
        ?int $entityId,
        ?int $municipalityId,
        ?int $establishmentId,
        ?array $oldValues,
        ?array $newValues,
        ?string $reason,
    ): void {
        try {
            OperationLog::query()->create([
                'user_id' => $user->id,
                'user_role' => $user->role?->value,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'municipality_id' => $municipalityId,
                'establishment_id' => $establishmentId,
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'reason' => $reason,
                'ip_address' => Request::ip(),
            ]);
        } catch (Throwable $e) {
            // Never let a logging failure break the action it's logging.
            Log::error('Failed to write operation log.', ['action' => $action, 'entity_type' => $entityType, 'exception' => $e]);
        }
    }
}
