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
        $rows = collect();

        foreach ($this->report['groups'] as $category => $group) {
            foreach ($group['rows'] as $row) {
                $rows->push([
                    $row['establishment'], $category, $row['source'] ?? '',
                    $row['male'], $row['female'], $row['total'],
                    $row['adults'], $row['children'], $row['seniors'],
                    $row['local'], $row['foreign'],
                ]);
            }

            $rows->push([
                "Subtotal — {$category}", '', '',
                $group['subtotal']['male'], $group['subtotal']['female'], $group['subtotal']['total'],
                $group['subtotal']['adults'], $group['subtotal']['children'], $group['subtotal']['seniors'],
                $group['subtotal']['local'], $group['subtotal']['foreign'],
            ]);
        }

        $grandTotal = $this->report['grand_total'];
        $rows->push([
            'GRAND TOTAL', '', '',
            $grandTotal['male'], $grandTotal['female'], $grandTotal['total'],
            $grandTotal['adults'], $grandTotal['children'], $grandTotal['seniors'],
            $grandTotal['local'], $grandTotal['foreign'],
        ]);

        return $rows;
    }

    public function title(): string
    {
        return substr($this->report['reference_number'] ?? 'Report', 0, 31);
    }
}
