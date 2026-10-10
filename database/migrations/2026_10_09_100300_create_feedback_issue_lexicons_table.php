<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Create tbl_feedback_issue_lexicons, the editable keyword and
 * phrase list that maps negative feedback to recurring issue categories.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only. fil_keyword is lowercase and may be a multi-word
     * phrase ("poorly maintained", "too many people"); each keyword maps to
     * exactly one category, so it is unique. fil_issue_category is one of
     * the keys of config('tourist_feedback.issue_categories').
     */
    public function up(): void
    {
        Schema::create('tbl_feedback_issue_lexicons', function (Blueprint $table) {
            $table->id('fil_id');
            $table->string('fil_keyword', 100)->unique();
            $table->string('fil_issue_category', 40);
            $table->unsignedTinyInteger('fil_weight')->default(1);
            $table->timestamp('fil_created_at')->nullable();
            $table->timestamp('fil_updated_at')->nullable();

            $table->index('fil_issue_category');
        });
    }

    /**
     * Drops the table with its unique index and index.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_feedback_issue_lexicons');
    }
};
