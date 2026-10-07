<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — add phone to users table.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The account-creation modal (Sub-stage B) has an optional Phone field
     * for the account holder, which Sub-stage A's migration didn't
     * anticipate — added here as its own follow-up rather than reopening
     * that migration.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('usr_phone')->nullable()->after('usr_password_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('usr_phone');
        });
    }
};
