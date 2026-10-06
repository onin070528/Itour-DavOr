<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — add reporting mode to listings table.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * I1: whether an establishment is managed digitally by its own account
     * or on paper by its LGU. Plain column name — `listings` keeps its own
     * existing naming convention (not ITD-prefixed; only brand-new tables
     * are), consistent with every other column added to it so far.
     * 'PAPER_LGU' is the only value the image-upload workflow checks for
     * today; 'DIGITAL' is the default for every establishment with its own
     * account.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('reporting_mode')->default('DIGITAL')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('reporting_mode');
        });
    }
};
