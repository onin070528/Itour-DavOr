<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Adds the formal feedback-form fields (visit details, aspect
 * ratings, recommendation, optional contact) to tbl_feedback.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_feedback', function (Blueprint $objTable) {
            $objTable->string('fbk_email')->nullable()->after('fbk_name');
            $objTable->date('fbk_visit_date')->nullable()->after('fbk_email');
            $objTable->string('fbk_visit_purpose', 40)->nullable()->after('fbk_visit_date');
            $objTable->string('fbk_visitor_origin', 20)->nullable()->after('fbk_visit_purpose');
            $objTable->json('fbk_aspect_ratings')->nullable()->after('fbk_rating');
            $objTable->boolean('fbk_would_recommend')->nullable()->after('fbk_aspect_ratings');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_feedback', function (Blueprint $objTable) {
            $objTable->dropColumn([
                'fbk_email', 'fbk_visit_date', 'fbk_visit_purpose',
                'fbk_visitor_origin', 'fbk_aspect_ratings', 'fbk_would_recommend',
            ]);
        });
    }
};
