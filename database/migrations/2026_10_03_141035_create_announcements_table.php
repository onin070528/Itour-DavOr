<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Create tbl_announcements for PTO-posted announcements, promotions and advisories.
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
        Schema::create('tblannouncements', function (Blueprint $table) {
            $table->id('ann_id');
            $table->string('ann_title');
            $table->text('ann_body');
            $table->string('ann_type');
            $table->date('ann_start_date')->nullable();
            $table->date('ann_end_date')->nullable();
            $table->boolean('ann_is_published')->default(false);
            $table->foreignId('ann_created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ann_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ann_created_at')->nullable();
            $table->timestamp('ann_updated_at')->nullable();

            $table->index(['ann_is_published', 'ann_start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblannouncements');
    }
};
