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
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_arrival_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable()->change();
            $table->timestamp('submitted_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Fails if any Draft (null submitted_by /
     * submitted_at) rows exist — submit or remove them first.
     */
    public function down(): void
    {
        Schema::table('monthly_arrival_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable(false)->change();
            $table->timestamp('submitted_at')->nullable(false)->change();
        });
    }
};
