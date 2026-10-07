<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — add role and organization to users table.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('email')->index();
            $table->string('organization_name')->nullable()->after('role');
            $table->string('organization_subtitle')->nullable()->after('organization_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'organization_name', 'organization_subtitle']);
        });
    }
};
