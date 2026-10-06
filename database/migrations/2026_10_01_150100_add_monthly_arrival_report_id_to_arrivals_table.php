<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — add monthly arrival report id to arrivals table.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a digitally-submitted month's individual arrival rows back to
     * the monthly_arrival_reports row they were aggregated into — stays
     * null until the establishment submits that period, and never set at
     * all for Manual/Paper reports (those don't have underlying per-visitor
     * arrival rows).
     */
    public function up(): void
    {
        Schema::table('arrivals', function (Blueprint $table) {
            $table->foreignId('monthly_arrival_report_id')->nullable()->after('listing_id')
                ->constrained('monthly_arrival_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('arrivals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('monthly_arrival_report_id');
        });
    }
};
