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
     * Run the migrations. Dated before the ITD naming standard rename
     * (2026_10_06_090000), so it works on both shapes of database: a fresh
     * install still has arrivals/users, while one that already ran the
     * rename has tbl_arrivals/tbl_users. The column is recorded_by in both.
     */
    public function up(): void
    {
        [$strTable, $strAfterColumn, $strUsersTable, $strUsersKey] = $this->targetNames();

        Schema::table($strTable, function (Blueprint $table) use ($strAfterColumn, $strUsersTable, $strUsersKey) {
            $table->foreignId('recorded_by')->nullable()->after($strAfterColumn)
                ->constrained($strUsersTable, $strUsersKey)->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        [$strTable] = $this->targetNames();

        Schema::table($strTable, function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
        });
    }

    /**
     * The arrivals table, the column to place recorded_by after, and the
     * users table and key, as they exist right now.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function targetNames(): array
    {
        return Schema::hasTable('tbl_arrivals')
            ? ['tbl_arrivals', 'arr_source', 'tbl_users', 'usr_id']
            : ['arrivals', 'source', 'users', 'id'];
    }
};
