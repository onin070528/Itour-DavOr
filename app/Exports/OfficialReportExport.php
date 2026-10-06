<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Excel export for the Official Report — the same columns and
 * category grouping/subtotals/grand total as resources/views/pdf/
 * official-report.blade.php, built from the same
 * App\Support\OfficialReportBuilder data shape.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OfficialReportExport implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $report  See OfficialReportBuilder's data shape.
     */
    public function __construct(private readonly array $report) {}

    public function headings(): array
    {
        return ['Establishment', 'Category', 'Source', 'Male', 'Female', 'Total', 'Adults', 'Children', 'Seniors', 'Local', 'Foreign'];
    }

    /**
     * @return Collection<int, array<int, mixed>>
     */
    public function collection(): Collection
    {
        $objRows = collect();

        foreach ($this->report['groups'] as $category => $arrGroup) {
            foreach ($arrGroup['rows'] as $arrRow) {
                $objRows->push([
                    $arrRow['establishment'], $category, $arrRow['source'] ?? '',
                    $arrRow['male'], $arrRow['female'], $arrRow['total'],
                    $arrRow['adults'], $arrRow['children'], $arrRow['seniors'],
                    $arrRow['local'], $arrRow['foreign'],
                ]);
            }

            $objRows->push([
                "Subtotal — {$category}", '', '',
                $arrGroup['subtotal']['male'], $arrGroup['subtotal']['female'], $arrGroup['subtotal']['total'],
                $arrGroup['subtotal']['adults'], $arrGroup['subtotal']['children'], $arrGroup['subtotal']['seniors'],
                $arrGroup['subtotal']['local'], $arrGroup['subtotal']['foreign'],
            ]);
        }

        $arrGrandTotal = $this->report['grand_total'];
        $objRows->push([
            'GRAND TOTAL', '', '',
            $arrGrandTotal['male'], $arrGrandTotal['female'], $arrGrandTotal['total'],
            $arrGrandTotal['adults'], $arrGrandTotal['children'], $arrGrandTotal['seniors'],
            $arrGrandTotal['local'], $arrGrandTotal['foreign'],
        ]);

        return $objRows;
    }

    public function title(): string
    {
        return substr($this->report['reference_number'] ?? 'Report', 0, 31);
    }
}
