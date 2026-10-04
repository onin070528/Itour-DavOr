<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Builds the generic data shape resources/views/pdf/official-report
 * .blade.php renders from, and validates that each row's Male/Female,
 * Adults/Children/Seniors, and Local/Foreign column groups all add up to
 * that row's Total before a report is allowed to generate — shared by the
 * LGU Submissions (municipal) and Provincial Reports PDFs so neither
 * re-implements the layout or the validation rule.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use Illuminate\Support\Collection;

class OfficialReportBuilder
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows  Each row must carry
     *                                                       'establishment', 'category', 'male', 'female', 'total', 'adults',
     *                                                       'children', 'seniors', 'local', 'foreign'.
     * @return array<int, string> One message per mismatched row/total — empty
     *                            when every group balances, which is the only time generation may
     *                            proceed.
     */
    public static function validateColumnSums(Collection $rows): array
    {
        $errors = [];

        foreach ($rows as $row) {
            $errors = [...$errors, ...self::validateRow($row['establishment'], $row)];
        }

        $errors = [...$errors, ...self::validateRow('Grand Total', self::sumRows($rows))];

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private static function validateRow(string $label, array $row): array
    {
        $errors = [];

        if ($row['total'] !== $row['male'] + $row['female']) {
            $errors[] = "{$label}: Male + Female (".($row['male'] + $row['female']).") does not equal Total ({$row['total']}).";
        }

        if ($row['total'] !== $row['adults'] + $row['children'] + $row['seniors']) {
            $errors[] = "{$label}: Adults + Children + Seniors (".($row['adults'] + $row['children'] + $row['seniors']).") does not equal Total ({$row['total']}).";
        }

        if ($row['total'] !== $row['local'] + $row['foreign']) {
            $errors[] = "{$label}: Local + Foreign (".($row['local'] + $row['foreign']).") does not equal Total ({$row['total']}).";
        }

        return $errors;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public static function sumRows(Collection $rows): array
    {
        return [
            'male' => (int) $rows->sum('male'),
            'female' => (int) $rows->sum('female'),
            'total' => (int) $rows->sum('total'),
            'adults' => (int) $rows->sum('adults'),
            'children' => (int) $rows->sum('children'),
            'seniors' => (int) $rows->sum('seniors'),
            'local' => (int) $rows->sum('local'),
            'foreign' => (int) $rows->sum('foreign'),
        ];
    }

    /**
     * Groups already-row-shaped data by category, each with its own subtotal
     * row, for the "table of establishments grouped by category with
     * subtotals and a grand total" layout.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<string, array{rows: Collection, subtotal: array}>
     */
    public static function groupByCategory(Collection $rows): Collection
    {
        return $rows->groupBy('category')->map(fn (Collection $group) => [
            'rows' => $group,
            'subtotal' => self::sumRows($group),
        ])->sortKeys();
    }

    /**
     * A short, URL-safe, hard-to-guess code for the public verification
     * page — collision risk is negligible at this volume, and the caller
     * (MunicipalReportsController::approve()) only ever assigns one once,
     * at verification time.
     */
    public static function generateVerificationCode(): string
    {
        return 'ITOUR-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    }
}
