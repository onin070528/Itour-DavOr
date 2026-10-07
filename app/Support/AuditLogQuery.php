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
    public static function validatedFilters(Request $objRequest): array
    {
        return $objRequest->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'event_type' => ['nullable', Rule::in(SecurityLog::EVENT_TYPES)],
            'action' => ['nullable', Rule::in(OperationLog::ACTIONS)],
            'municipality_id' => ['nullable', 'integer', Rule::exists('tbl_municipalities', 'mun_id')],
        ]);
    }

    public static function securityLogs(User $objViewer, array $arrFilters): LengthAwarePaginator
    {
        return self::securityQuery($objViewer, $arrFilters)->latest('sec_id')->paginate(self::PER_PAGE)->withQueryString();
    }

    public static function operationLogs(User $objViewer, array $arrFilters): LengthAwarePaginator
    {
        return self::operationQuery($objViewer, $arrFilters)->latest('opl_id')->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Capped, unpaginated — for CSV export only. $arrFilters is the same
     * role-scoped, validated filter set the table itself is showing, so an
     * export can never contain rows the viewer couldn't already see.
     */
    public static function securityLogsForExport(User $objViewer, array $arrFilters): Collection
    {
        return self::securityQuery($objViewer, $arrFilters)->latest('sec_id')->limit(self::EXPORT_LIMIT)->get();
    }

    public static function operationLogsForExport(User $objViewer, array $arrFilters): Collection
    {
        return self::operationQuery($objViewer, $arrFilters)->latest('opl_id')->limit(self::EXPORT_LIMIT)->get();
    }

    /**
     * @return array{total: int, failed_logins: int, access_denied: int}
     */
    public static function securitySummary(User $objViewer): array
    {
        $objBase = SecurityLog::query()->visibleTo($objViewer)->where('sec_created_at', '>=', now()->subDays(30));

        return [
            'total' => (clone $objBase)->count(),
            'failed_logins' => (clone $objBase)->where('sec_event_type', 'login_failed')->count(),
            'access_denied' => (clone $objBase)->where('sec_event_type', 'access_denied')->count(),
        ];
    }

    /**
     * @return array{total: int, approvals: int, returned_or_rejected: int}
     */
    public static function operationSummary(User $objViewer): array
    {
        $objBase = OperationLog::query()->visibleTo($objViewer)->where('opl_created_at', '>=', now()->subDays(30));

        return [
            'total' => (clone $objBase)->count(),
            'approvals' => (clone $objBase)->where('opl_action', 'approve')->count(),
            'returned_or_rejected' => (clone $objBase)->whereIn('opl_action', ['return', 'reject'])->count(),
        ];
    }

    private static function securityQuery(User $objViewer, array $arrFilters): Builder
    {
        [$dtmFrom, $dtmTo] = self::dateRange($arrFilters['date_from'] ?? null, $arrFilters['date_to'] ?? null);

        $objQuery = SecurityLog::query()
            ->visibleTo($objViewer)
            ->with(['user', 'targetUser'])
            ->whereBetween('sec_created_at', [$dtmFrom, $dtmTo])
            ->when($arrFilters['event_type'] ?? null, fn (Builder $objQuery, string $strType) => $objQuery->where('sec_event_type', $strType))
            ->when(
                $objViewer->usr_role === UserRole::PtoAdministrator && ! empty($arrFilters['municipality_id']),
                fn (Builder $objQuery) => $objQuery->where('mun_id', $arrFilters['municipality_id'])
            )
            ->when($arrFilters['search'] ?? null, function (Builder $objQuery, string $strSearch) {
                $objQuery->where(function (Builder $objQuery2) use ($strSearch) {
                    $objQuery2->where('sec_attempted_email', 'like', "%{$strSearch}%")
                        ->orWhereHas('user', fn (Builder $objQuery3) => $objQuery3->where('usr_name', 'like', "%{$strSearch}%")->orWhere('usr_email', 'like', "%{$strSearch}%"))
                        ->orWhereHas('targetUser', fn (Builder $objQuery3) => $objQuery3->where('usr_name', 'like', "%{$strSearch}%")->orWhere('usr_email', 'like', "%{$strSearch}%"));
                });
            });

        // Establishment must never receive ip_address/user_agent in the
        // response at all — excluded from the SELECT itself, not just
        // hidden in the Blade view.
        if ($objViewer->usr_role === UserRole::Establishment) {
            $objQuery->select(['sec_id', 'sec_event_type', 'usr_id', 'sec_attempted_email', 'sec_target_user_id', 'mun_id', 'sec_details', 'sec_created_at']);
        }

        return $objQuery;
    }

    private static function operationQuery(User $objViewer, array $arrFilters): Builder
    {
        [$dtmFrom, $dtmTo] = self::dateRange($arrFilters['date_from'] ?? null, $arrFilters['date_to'] ?? null);

        return OperationLog::query()
            ->visibleTo($objViewer)
            ->with(['user', 'establishment'])
            ->whereBetween('opl_created_at', [$dtmFrom, $dtmTo])
            ->when($arrFilters['action'] ?? null, fn (Builder $objQuery, string $strAction) => $objQuery->where('opl_action', $strAction))
            ->when(
                $objViewer->usr_role === UserRole::PtoAdministrator && ! empty($arrFilters['municipality_id']),
                fn (Builder $objQuery) => $objQuery->where('mun_id', $arrFilters['municipality_id'])
            )
            ->when($arrFilters['search'] ?? null, function (Builder $objQuery, string $strSearch) {
                $objQuery->where(function (Builder $objQuery2) use ($strSearch) {
                    $objQuery2->whereHas('user', fn (Builder $objQuery3) => $objQuery3->where('usr_name', 'like', "%{$strSearch}%")->orWhere('usr_email', 'like', "%{$strSearch}%"))
                        ->orWhere('opl_old_values', 'like', "%{$strSearch}%")
                        ->orWhere('opl_new_values', 'like', "%{$strSearch}%")
                        ->when(is_numeric($strSearch), fn (Builder $objQuery3) => $objQuery3->orWhere('opl_entity_id', (int) $strSearch));
                });
            });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function dateRange(?string $strFrom, ?string $strTo): array
    {
        return [
            $strFrom ? Carbon::parse($strFrom)->startOfDay() : now()->subDays(30)->startOfDay(),
            $strTo ? Carbon::parse($strTo)->endOfDay() : now()->endOfDay(),
        ];
    }
}
