<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Link listings to tbl_categories and add Travel & Tours / Others fields.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the fixed-lookup category_id alongside the existing free-text
     * `category` column (kept, not dropped, so no consumer breaks before
     * Stage 2 cuts every reader over to the relation). `type` only applies
     * to Travel & Tours listings ('Tour Operator' | 'Tour Guide');
     * `license_number` and `accreditation_status` are guide fields;
     * `category_note` is the required note captured when category is
     * Others. Guide rows never get lat/lng or a QR identifier — enforced at
     * the application layer (Listing::isQrEnabled(), form requests), not by
     * a DB constraint, since destinations/establishments share this same
     * table and column set.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('cat_id')->nullable()->after('category')
                ->constrained('tblcategories', 'cat_id')->nullOnDelete();
            $table->string('type')->nullable()->after('cat_id');
            $table->string('license_number')->nullable()->after('type');
            $table->string('accreditation_status')->nullable()->after('license_number');
            $table->string('category_note')->nullable()->after('accreditation_status');

            $table->index('cat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cat_id');
            $table->dropColumn(['type', 'license_number', 'accreditation_status', 'category_note']);
        });
    }
};
