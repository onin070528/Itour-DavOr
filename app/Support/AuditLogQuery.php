<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared, role-scoped query building for the Audit Logs / Activity
 * Log page — used by Pto\AuditLogsController, Lgu\AuditLogsController, and
 * Establishment\ActivityLogController so the filtering/scoping/pagination
 * logic exists in exactly one place. Search/filter input is always validated
 * server-side here; role scoping always comes from the viewer, never from a
 * request parameter (see SecurityLog::scopeVisibleTo / OperationLog::scopeVisibleTo).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Enums\UserRole;
use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AuditLogQuery
{
    public const PER_PAGE = 20;

    public const EXPORT_LIMIT = 10000;

    /**
     * @return array{search: ?string, date_from: ?string, date_to: ?string, event_type: ?string, action: ?string, municipality_id: ?int}
     */
    public static function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'event_type' => ['nullable', Rule::in(SecurityLog::EVENT_TYPES)],
            'action' => ['nullable', Rule::in(OperationLog::ACTIONS)],
            'municipality_id' => ['nullable', 'integer', Rule::exists('municipalities', 'id')],
        ]);
    }

    public static function securityLogs(User $viewer, array $filters): LengthAwarePaginator
    {
        return self::securityQuery($viewer, $filters)->latest('id')->paginate(self::PER_PAGE)->withQueryString();
    }

    public static function operationLogs(User $viewer, array $filters): LengthAwarePaginator
    {
        return self::operationQuery($viewer, $filters)->latest('id')->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Capped, unpaginated — for CSV export only. $filters is the same
     * role-scoped, validated filter set the table itself is showing, so an
     * export can never contain rows the viewer couldn't already see.
     */
    public static function securityLogsForExport(User $viewer, array $filters): Collection
    {
        return self::securityQuery($viewer, $filters)->latest('id')->limit(self::EXPORT_LIMIT)->get();
    }

    public static function operationLogsForExport(User $viewer, array $filters): Collection
    {
        return self::operationQuery($viewer, $filters)->latest('id')->limit(self::EXPORT_LIMIT)->get();
    }

    /**
     * @return array{total: int, failed_logins: int, access_denied: int}
     */
    public static function securitySummary(User $viewer): array
    {
        $base = SecurityLog::query()->visibleTo($viewer)->where('created_at', '>=', now()->subDays(30));

        return [
            'total' => (clone $base)->count(),
            'failed_logins' => (clone $base)->where('event_type', 'login_failed')->count(),
            'access_denied' => (clone $base)->where('event_type', 'access_denied')->count(),
        ];
    }

    /**
     * @return array{total: int, approvals: int, returned_or_rejected: int}
     */
    public static function operationSummary(User $viewer): array
    {
        $base = OperationLog::query()->visibleTo($viewer)->where('created_at', '>=', now()->subDays(30));

        return [
            'total' => (clone $base)->count(),
            'approvals' => (clone $base)->where('action', 'approve')->count(),
            'returned_or_rejected' => (clone $base)->whereIn('action', ['return', 'reject'])->count(),
        ];
    }

    private static function securityQuery(User $viewer, array $filters): Builder
    {
        [$from, $to] = self::dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        $query = SecurityLog::query()
            ->visibleTo($viewer)
            ->with(['user', 'targetUser'])
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['event_type'] ?? null, fn (Builder $q, string $type) => $q->where('event_type', $type))
            ->when(
                $viewer->role === UserRole::PtoAdministrator && ! empty($filters['municipality_id']),
                fn (Builder $q) => $q->where('municipality_id', $filters['municipality_id'])
            )
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $q->where(function (Builder $q2) use ($search) {
                    $q2->where('attempted_email', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $q3) => $q3->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('targetUser', fn (Builder $q3) => $q3->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            });

        // Establishment must never receive ip_address/user_agent in the
        // response at all — excluded from the SELECT itself, not just
        // hidden in the Blade view.
        if ($viewer->role === UserRole::Establishment) {
            $query->select(['id', 'event_type', 'user_id', 'attempted_email', 'target_user_id', 'municipality_id', 'details', 'created_at']);
        }

        return $query;
    }

    private static function operationQuery(User $viewer, array $filters): Builder
    {
        [$from, $to] = self::dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        return OperationLog::query()
            ->visibleTo($viewer)
            ->with(['user', 'establishment'])
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['action'] ?? null, fn (Builder $q, string $action) => $q->where('action', $action))
            ->when(
                $viewer->role === UserRole::PtoAdministrator && ! empty($filters['municipality_id']),
                fn (Builder $q) => $q->where('municipality_id', $filters['municipality_id'])
            )
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $q->where(function (Builder $q2) use ($search) {
                    $q2->whereHas('user', fn (Builder $q3) => $q3->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhere('old_values', 'like', "%{$search}%")
                        ->orWhere('new_values', 'like', "%{$search}%")
                        ->when(is_numeric($search), fn (Builder $q3) => $q3->orWhere('entity_id', (int) $search));
                });
            });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function dateRange(?string $from, ?string $to): array
    {
        return [
            $from ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay(),
            $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay(),
        ];
    }
}
