<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner / manager of an establishment, captured by the LGU "Establishment
     * Information" form. Nullable because destinations have no owner and
     * establishments registered before this form existed never recorded one.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('owner_name');
        });
    }
};
