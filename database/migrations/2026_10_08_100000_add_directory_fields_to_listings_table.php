<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — Objective 3 destination fields and directory/nearby
 * search indexes on tbl_listings.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only — every new column is nullable with no default, so no
     * existing row is changed:
     *  - lst_visitor_information: what a visitor should know before going
     *    (permits, what to bring, safety notes).
     *  - lst_entrance_fee: free text, e.g. "PHP 50 adults, PHP 20 children".
     *  - lst_managing_level: App\Enums\ManagingLevel ('lgu' | 'pto'); NULL
     *    reads as LGU-managed, the behavior before this column existed.
     *  - lst_created_by / lst_updated_by: the accounts that created and last
     *    updated the record (same pattern as tbl_users.usr_created_by).
     * Indexes: mun_id and lst_status for directory filtering, and
     * (lst_lat, lst_lng) for the nearby-search bounding-box prefilter.
     */
    public function up(): void
    {
        Schema::table('tbl_listings', function (Blueprint $table) {
            $table->text('lst_visitor_information')->nullable();
            $table->string('lst_entrance_fee')->nullable();
            $table->string('lst_managing_level')->nullable();
            $table->foreignId('lst_created_by')->nullable()
                ->constrained('tbl_users', 'usr_id')->nullOnDelete();
            $table->foreignId('lst_updated_by')->nullable()
                ->constrained('tbl_users', 'usr_id')->nullOnDelete();

            $table->index('mun_id');
            $table->index('lst_status');
            $table->index(['lst_lat', 'lst_lng']);
        });
    }

    public function down(): void
    {
        Schema::table('tbl_listings', function (Blueprint $table) {
            $table->dropIndex(['lst_lat', 'lst_lng']);
            $table->dropIndex(['lst_status']);
            $table->dropIndex(['mun_id']);

            $table->dropConstrainedForeignId('lst_updated_by');
            $table->dropConstrainedForeignId('lst_created_by');
            $table->dropColumn(['lst_managing_level', 'lst_entrance_fee', 'lst_visitor_information']);
        });
    }
};
