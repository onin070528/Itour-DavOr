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
     * @param  Collection<int, array<string, mixed>>  $objRows  Each row must carry
     *                                                          'establishment', 'category', 'male', 'female', 'total', 'adults',
     *                                                          'children', 'seniors', 'local', 'foreign'.
     * @return array<int, string> One message per mismatched row/total — empty
     *                            when every group balances, which is the only time generation may
     *                            proceed.
     */
    public static function validateColumnSums(Collection $objRows): array
    {
        $arrErrors = [];

        foreach ($objRows as $arrRow) {
            $arrErrors = [...$arrErrors, ...self::validateRow($arrRow['establishment'], $arrRow)];
        }

        $arrErrors = [...$arrErrors, ...self::validateRow('Grand Total', self::sumRows($objRows))];

        return $arrErrors;
    }

    /**
     * @param  array<string, mixed>  $arrRow
     * @return array<int, string>
     */
    private static function validateRow(string $strLabel, array $arrRow): array
    {
        $arrErrors = [];

        if ($arrRow['total'] !== $arrRow['male'] + $arrRow['female']) {
            $arrErrors[] = "{$strLabel}: Male + Female (".($arrRow['male'] + $arrRow['female']).") does not equal Total ({$arrRow['total']}).";
        }

        if ($arrRow['total'] !== $arrRow['adults'] + $arrRow['children'] + $arrRow['seniors']) {
            $arrErrors[] = "{$strLabel}: Adults + Children + Seniors (".($arrRow['adults'] + $arrRow['children'] + $arrRow['seniors']).") does not equal Total ({$arrRow['total']}).";
        }

        if ($arrRow['total'] !== $arrRow['local'] + $arrRow['foreign']) {
            $arrErrors[] = "{$strLabel}: Local + Foreign (".($arrRow['local'] + $arrRow['foreign']).") does not equal Total ({$arrRow['total']}).";
        }

        return $arrErrors;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $objRows
     * @return array<string, int>
     */
    public static function sumRows(Collection $objRows): array
    {
        return [
            'male' => (int) $objRows->sum('male'),
            'female' => (int) $objRows->sum('female'),
            'total' => (int) $objRows->sum('total'),
            'adults' => (int) $objRows->sum('adults'),
            'children' => (int) $objRows->sum('children'),
            'seniors' => (int) $objRows->sum('seniors'),
            'local' => (int) $objRows->sum('local'),
            'foreign' => (int) $objRows->sum('foreign'),
        ];
    }

    /**
     * Groups already-row-shaped data by category, each with its own subtotal
     * row, for the "table of establishments grouped by category with
     * subtotals and a grand total" layout.
     *
     * @param  Collection<int, array<string, mixed>>  $objRows
     * @return Collection<string, array{rows: Collection, subtotal: array}>
     */
    public static function groupByCategory(Collection $objRows): Collection
    {
        return $objRows->groupBy('category')->map(fn (Collection $objGroup) => [
            'rows' => $objGroup,
            'subtotal' => self::sumRows($objGroup),
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
