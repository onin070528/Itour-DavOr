<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Create tbl_feedback_issues, the recurring issue categories
 * detected in one negative tourist feedback row.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only. One row per (feedback, category): the unique pair lets
     * "Common Concerns" count how many feedback rows mention a category,
     * never how many keywords matched. fbi_matched_keyword keeps the
     * keyword or phrase that triggered the category, so each detection can
     * be explained. These rows are derived from their feedback, so they
     * cascade if a feedback row is ever deleted (feedback itself is never
     * deleted by normal workflow).
     */
    public function up(): void
    {
        Schema::create('tbl_feedback_issues', function (Blueprint $table) {
            $table->id('fbi_id');
            $table->foreignId('fbk_id')->constrained('tbl_feedbacks', 'fbk_id')->cascadeOnDelete();
            $table->string('fbi_issue_category', 40);
            $table->string('fbi_matched_keyword', 100)->nullable();
            $table->timestamp('fbi_created_at')->nullable();

            $table->unique(['fbk_id', 'fbi_issue_category']);
            $table->index('fbi_issue_category');
        });
    }

    /**
     * Drops the table with its unique index, index, and foreign key.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_feedback_issues');
    }
};
