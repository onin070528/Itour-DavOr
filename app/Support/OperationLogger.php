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
     * @param  ?array{old: array<string, mixed>, new: array<string, mixed>}  $diff  From diff() — null if nothing meaningful changed.
     */
    public static function updated(User $user, string $entityType, int $entityId, ?int $municipalityId, ?int $establishmentId, ?array $diff): void
    {
        self::write('update', $user, $entityType, $entityId, $municipalityId, $establishmentId, $diff['old'] ?? null, $diff['new'] ?? null, null);
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
    public static function returned(User $user, string $entityType, int $entityId, string $reason, ?int $municipalityId, ?array $diff = null): void
    {
        self::write('return', $user, $entityType, $entityId, $municipalityId, null, $diff['old'] ?? null, $diff['new'] ?? null, $reason);
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
