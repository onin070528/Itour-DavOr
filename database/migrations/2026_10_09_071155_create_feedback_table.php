<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — create feedback table (tourist feedback with its computed sentiment).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per piece of tourist feedback about a destination or
     * establishment (a `tbl_listings` row). `fbk_sentiment` /
     * `fbk_polarity` / `fbk_language` are computed once at submission by
     * App\Services\SentimentAnalyzer and stored, so the LGU/PTO pages only
     * read them.
     */
    public function up(): void
    {
        Schema::create('tbl_feedback', function (Blueprint $objTable) {
            $objTable->id('fbk_id');
            $objTable->foreignId('lst_id')->constrained('tbl_listings', 'lst_id')->cascadeOnDelete();
            $objTable->string('fbk_name')->nullable();
            $objTable->unsignedTinyInteger('fbk_rating');
            $objTable->text('fbk_text');
            $objTable->string('fbk_language', 20)->default('English');
            $objTable->string('fbk_sentiment', 20);
            $objTable->decimal('fbk_polarity', 4, 2)->default(0);
            $objTable->timestamp('fbk_created_at')->nullable();
            $objTable->timestamp('fbk_updated_at')->nullable();

            $objTable->index(['lst_id', 'fbk_created_at']);
            $objTable->index('fbk_sentiment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_feedback');
    }
};
