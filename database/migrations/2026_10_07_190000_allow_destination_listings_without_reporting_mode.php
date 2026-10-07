<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Allows destination-only listings to have no reporting method while preserving the establishment default.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\ReportingMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Destination-only records are not establishments and must not inherit
     * the establishment's Manual/Paper reporting default.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('reporting_mode')
                ->nullable()
                ->default(ReportingMethod::ManualPaper->value)
                ->change();
        });
    }

    /**
     * Restore the non-nullable establishment column. Null destination rows
     * are assigned the historical default before the constraint is restored.
     */
    public function down(): void
    {
        DB::table('listings')
            ->whereNull('reporting_mode')
            ->update(['reporting_mode' => ReportingMethod::ManualPaper->value]);

        Schema::table('listings', function (Blueprint $table) {
            $table->string('reporting_mode')
                ->nullable(false)
                ->default(ReportingMethod::ManualPaper->value)
                ->change();
        });
    }
};
