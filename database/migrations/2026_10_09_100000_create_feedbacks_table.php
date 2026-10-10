<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Create tbl_feedbacks, the public tourist feedback submitted for a
 * published destination or tourism establishment listing, with its
 * translation and lexicon-based sentiment result (Objective 4).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: a new table, no existing table is changed.
     *
     *  - lst_id references the existing tbl_listings row (destinations and
     *    establishments share that table; the listing's current
     *    lst_category tells the two apart). RESTRICT on delete: feedback
     *    history is never removed because of its listing; unpublishing,
     *    archiving, or suspending a listing only changes lst_status.
     *  - fbk_original_text is never overwritten; fbk_translated_text holds
     *    the English text the sentiment is computed from.
     *  - fbk_consent_at is NOT NULL: consent is required for every public
     *    submission and this is a new table with no older rows to support.
     *  - The analysis columns stay NULL until fbk_status is 'analyzed'.
     *  - No IP address, account, or location data is stored.
     */
    public function up(): void
    {
        Schema::create('tbl_feedbacks', function (Blueprint $table) {
            $table->id('fbk_id');
            $table->foreignId('lst_id')->constrained('tbl_listings', 'lst_id')->restrictOnDelete();
            $table->string('fbk_tourist_name', 100)->nullable();
            $table->text('fbk_original_text');
            $table->text('fbk_translated_text')->nullable();
            $table->string('fbk_detected_language', 20)->nullable();
            $table->date('fbk_visit_date')->nullable();
            $table->timestamp('fbk_consent_at');
            $table->char('fbk_content_hash', 64);
            $table->string('fbk_status', 20)->default('pending');
            $table->unsignedSmallInteger('fbk_positive_count')->nullable();
            $table->unsignedSmallInteger('fbk_negative_count')->nullable();
            $table->unsignedSmallInteger('fbk_total_word_count')->nullable();
            $table->decimal('fbk_sentiment_score', 6, 4)->nullable();
            $table->string('fbk_sentiment', 10)->nullable();
            $table->json('fbk_matched_terms')->nullable();
            $table->string('fbk_failure_reason', 255)->nullable();
            $table->timestamp('fbk_analyzed_at')->nullable();
            $table->timestamp('fbk_created_at')->nullable();
            $table->timestamp('fbk_updated_at')->nullable();

            $table->index('lst_id');
            $table->index('fbk_status');
            $table->index('fbk_sentiment');
            $table->index('fbk_created_at');
            $table->index(['fbk_content_hash', 'fbk_created_at']);
        });

        // Postgres-only: SQLite (the test suite's driver) can't ADD
        // CONSTRAINT via ALTER TABLE. The enums App\Enums\
        // FeedbackAnalysisStatus and SentimentClassification enforce the
        // same values in application code on every driver.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tbl_feedbacks ADD CONSTRAINT chk_fbk_status CHECK (fbk_status IN ('pending', 'analyzed', 'failed', 'rejected'))");
            DB::statement("ALTER TABLE tbl_feedbacks ADD CONSTRAINT chk_fbk_sentiment CHECK (fbk_sentiment IS NULL OR fbk_sentiment IN ('positive', 'neutral', 'negative'))");
            DB::statement('ALTER TABLE tbl_feedbacks ADD CONSTRAINT chk_fbk_sentiment_score CHECK (fbk_sentiment_score IS NULL OR fbk_sentiment_score BETWEEN -1 AND 1)');
        }
    }

    /**
     * Drops the table with its indexes, CHECK constraints, and foreign key.
     * Only Objective 4 feedback rows are removed; no existing data is touched.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_feedbacks');
    }
};
