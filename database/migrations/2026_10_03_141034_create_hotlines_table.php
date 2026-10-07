<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Create tbl_hotlines, the province-wide emergency hotline directory.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Province-wide hotline numbers (Hotlines public page) — PTO-managed
     * only, no LGU/municipality scoping column by design.
     */
    public function up(): void
    {
        Schema::create('tblhotlines', function (Blueprint $table) {
            $table->id('hot_id');
            $table->string('hot_agency_name');
            $table->string('hot_agency_type');
            $table->string('hot_contact_number');
            $table->string('hot_scope')->default('Province-wide');
            $table->boolean('hot_is_24_7')->default(false);
            $table->boolean('hot_is_active')->default(true);
            $table->unsignedSmallInteger('hot_sort_order')->default(0);
            $table->foreignId('hot_created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hot_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hot_created_at')->nullable();
            $table->timestamp('hot_updated_at')->nullable();

            $table->index(['hot_is_active', 'hot_sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblhotlines');
    }
};
