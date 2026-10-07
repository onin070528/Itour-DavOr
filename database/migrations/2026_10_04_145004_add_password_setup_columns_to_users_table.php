<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — add password setup columns to users table.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * First-login forced password change. `usr_municipality_id` was asked
     * for in the originating spec, but `users.municipality_id` already
     * exists and already serves exactly that purpose (added by
     * 2026_09_21_140553_add_scoping_columns_to_users_table.php, used
     * throughout the app's RBAC scoping) — adding a second column for the
     * same thing would duplicate it, so only the two genuinely new columns
     * are added here. Existing rows default to
     * usr_must_change_password = false, so no current account is ever
     * redirected to the first-login flow.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('usr_must_change_password')->default(false)->after('password');
            $table->timestamp('usr_password_changed_at')->nullable()->after('usr_must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['usr_must_change_password', 'usr_password_changed_at']);
        });
    }
};
