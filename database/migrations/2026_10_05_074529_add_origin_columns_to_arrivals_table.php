<?php

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
        Schema::table('arrivals', function (Blueprint $table) {
            $table->string('local_origin_scope')->nullable()->after('party_foreign');
            $table->string('local_origin_place', 100)->nullable()->after('local_origin_scope');
            $table->string('foreign_country', 100)->nullable()->after('local_origin_place');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arrivals', function (Blueprint $table) {
            $table->dropColumn(['local_origin_scope', 'local_origin_place', 'foreign_country']);
        });
    }
};
