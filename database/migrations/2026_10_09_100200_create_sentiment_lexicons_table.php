<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Create tbl_sentiment_lexicons, the editable tourism sentiment
 * word list used by the lexicon-based polarity scoring.
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
     * Additive only. slx_word is a single lowercase word, unique.
     * slx_polarity is 'positive' or 'negative'. slx_weight is seeded at 1:
     * the documented formula S = (P - N) / T counts matched words, so the
     * weight is stored for the lexicon record but does not change S.
     */
    public function up(): void
    {
        Schema::create('tbl_sentiment_lexicons', function (Blueprint $table) {
            $table->id('slx_id');
            $table->string('slx_word', 50)->unique();
            $table->string('slx_polarity', 10);
            $table->unsignedTinyInteger('slx_weight')->default(1);
            $table->timestamp('slx_created_at')->nullable();
            $table->timestamp('slx_updated_at')->nullable();
        });

        // Postgres-only, as in the tbl_feedbacks migration; the
        // SentimentLexicon model constants enforce it on every driver.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tbl_sentiment_lexicons ADD CONSTRAINT chk_slx_polarity CHECK (slx_polarity IN ('positive', 'negative'))");
        }
    }

    /**
     * Drops the table with its unique index and CHECK constraint.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_sentiment_lexicons');
    }
};
