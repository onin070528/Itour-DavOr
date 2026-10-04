<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Create tblcategories, the fixed lookup of establishment categories.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed category lookup (tblcategories) replacing the free-text
     * `listings.category` string. cat_is_qr_enabled is the data half of the
     * QR rule (see Listing::isQrEnabled()) — provisional per A8, true for
     * every category except Others, and never hard-coded in application
     * code.
     */
    public function up(): void
    {
        Schema::create('tblcategories', function (Blueprint $table) {
            $table->id('cat_id');
            $table->string('cat_name');
            $table->unsignedSmallInteger('cat_sort_order')->default(0);
            $table->boolean('cat_is_active')->default(true);
            $table->boolean('cat_is_qr_enabled')->default(false);
            $table->timestamp('cat_created_at')->nullable();
            $table->timestamp('cat_updated_at')->nullable();

            $table->unique('cat_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblcategories');
    }
};
