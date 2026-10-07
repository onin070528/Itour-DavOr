<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Records which establishment user encoded a front-desk (staff)
 * arrival, so every arrival keeps its source, encoder, and timestamp. Null
 * for QR self-check-in rows (the tourist has no account) and for rows that
 * predate this column. Named to match the arrivals table's existing
 * unprefixed columns.
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
        Schema::table('arrivals', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('source')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arrivals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
        });
    }
};
