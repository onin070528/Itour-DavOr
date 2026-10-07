<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Adds the per-establishment QR check-in switch to `listings`.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-establishment QR switch, checked by
     * Listing::isAcceptingRegistrations() on top of the category-level
     * cat_is_qr_enabled switch. Defaults to true so every existing
     * establishment keeps its current QR behavior. ITD-prefixed (`lst_`)
     * because it is a genuinely new column (ITD 10.3), mirroring
     * cat_is_qr_enabled. Additive only — down() drops just this column.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('lst_is_qr_enabled')->default(true)->after('reporting_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('lst_is_qr_enabled');
        });
    }
};
