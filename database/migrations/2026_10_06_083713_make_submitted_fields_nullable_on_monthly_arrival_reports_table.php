<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Lets a MonthlyArrivalReport exist as a Draft before it is
 * submitted — submitted_by/submitted_at stay null until the owner (the
 * establishment, or the LGU encoding a paper report on its behalf) submits
 * it. Additive only: no data is changed, existing rows keep their values.
 * The Draft's creator and creation time are recorded in operation_logs.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. This migration is dated before the ITD naming
     * standard rename (2026_10_06_090000), so it works on both shapes of
     * database: a fresh install still has the old table and column names,
     * while one that already ran the rename has tbl_monthly_arrival_reports
     * with the mar_ prefix.
     */
    public function up(): void
    {
        [$strTable, $strSubmittedBy, $strSubmittedAt] = $this->targetNames();

        Schema::table($strTable, function (Blueprint $table) use ($strSubmittedBy, $strSubmittedAt) {
            $table->unsignedBigInteger($strSubmittedBy)->nullable()->change();
            $table->timestamp($strSubmittedAt)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Fails if any Draft (null submitted_by /
     * submitted_at) rows exist — submit or remove them first.
     */
    public function down(): void
    {
        [$strTable, $strSubmittedBy, $strSubmittedAt] = $this->targetNames();

        Schema::table($strTable, function (Blueprint $table) use ($strSubmittedBy, $strSubmittedAt) {
            $table->unsignedBigInteger($strSubmittedBy)->nullable(false)->change();
            $table->timestamp($strSubmittedAt)->nullable(false)->change();
        });
    }

    /**
     * The table and the two column names as they exist right now.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function targetNames(): array
    {
        return Schema::hasTable('tbl_monthly_arrival_reports')
            ? ['tbl_monthly_arrival_reports', 'mar_submitted_by', 'mar_submitted_at']
            : ['monthly_arrival_reports', 'submitted_by', 'submitted_at'];
    }
};
