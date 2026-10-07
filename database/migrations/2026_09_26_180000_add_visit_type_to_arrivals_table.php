<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — add visit type to arrivals table.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the party is on a same-day visit or staying overnight — asked
     * by both arrival forms (staff "Record Arrival" wizard and the public
     * QR self-checkin) alongside the existing party_* companion counters.
     */
    public function up(): void
    {
        Schema::table('arrivals', function (Blueprint $table) {
            $table->string('visit_type')->nullable()->after('classification');
        });
    }

    public function down(): void
    {
        Schema::table('arrivals', function (Blueprint $table) {
            $table->dropColumn('visit_type');
        });
    }
};
