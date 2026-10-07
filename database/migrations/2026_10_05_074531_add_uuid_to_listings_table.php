<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — add uuid to listings table.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Adds a `uuid` identifier to `listings`, used as the public check-in
     * URL segment (/checkin/{uuid}) instead of the slug, so a listing's
     * public-facing slug can change without breaking QR codes already
     * printed and posted at the establishment. Backfilled in this same
     * migration (rather than a separate data migration) because the unique
     * index below cannot be added until every existing row has a value.
     *
     * Left nullable at the DB level (both Postgres and SQLite allow more
     * than one NULL in a unique column) rather than NOT NULL, because a
     * couple of existing tests insert listings via DB::table() directly,
     * bypassing the Listing::creating() hook that assigns new rows their
     * uuid. Every row reachable through the app is still guaranteed one.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        DB::table('listings')->whereNull('uuid')->orderBy('id')->each(function ($listing) {
            DB::table('listings')->where('id', $listing->id)->update(['uuid' => Str::uuid()->toString()]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
