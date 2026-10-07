<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Makes Manual/Paper (PAPER_LGU) the default reporting method for newly registered establishments.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\ReportingMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Column default only — no existing row changes. Creating an
     * establishment never creates an account, so a new establishment
     * cannot report online until the LGU activates one; Manual/Paper is
     * therefore the safe starting point (App\Enums\ReportingMethod::
     * default()). Paths that create an establishment together with its
     * account set DIGITAL explicitly.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('reporting_mode')->default(ReportingMethod::ManualPaper->value)->change();
        });
    }

    /**
     * Restores the previous DIGITAL default. Rows are untouched.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('reporting_mode')->default(ReportingMethod::OnlineItour->value)->change();
        });
    }
};
