<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coordinates for the "Find Places Near You" Mapbox map on the landing
     * page — nullable since not every future listing will have them plotted.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->double('lat')->nullable()->after('barangay');
            $table->double('lng')->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
