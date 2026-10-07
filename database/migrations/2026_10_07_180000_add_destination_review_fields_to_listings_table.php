<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Destination listing review state on the existing listings row (Phase 5, Option C — no second listing row).
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only. New columns use the lst_ prefix of the other
     * listing-specific additions (lst_is_qr_enabled):
     *  - lst_review_remarks: the PTO's required remarks from its latest
     *    Return for Correction, shown to the LGU until it resubmits.
     *  - lst_pending_changes: edits to public destination content of a
     *    Published listing, held here (field => proposed value) so the
     *    published version stays live until the PTO approves them.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->text('lst_review_remarks')->nullable()->after('status');
            $table->json('lst_pending_changes')->nullable()->after('lst_review_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['lst_review_remarks', 'lst_pending_changes']);
        });
    }
};
