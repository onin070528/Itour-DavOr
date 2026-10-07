<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — add municipality id to municipal reports table.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RBAC-facing FK alongside the existing `municipality` string column
     * (kept as-is — still read by existing PTO views/tests). Backfilled for
     * existing rows by Database\Seeders\RbacScopeBackfillSeeder; set
     * directly on every new row going forward by the LGU consolidation
     * action. restrictOnDelete, matching listings.municipality_id's
     * reasoning — a municipality with reports attached should never be
     * silently orphaned.
     */
    public function up(): void
    {
        Schema::table('municipal_reports', function (Blueprint $table) {
            $table->foreignId('municipality_id')->nullable()->after('municipality')
                ->constrained('municipalities')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('municipal_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('municipality_id');
        });
    }
};
