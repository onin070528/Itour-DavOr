<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The first real, id-backed source of truth for Davao Oriental's
     * municipalities — RBAC (users.municipality_id, listings.municipality_id)
     * and future modules should join against this table, not hard-code or
     * string-match municipality names. App\Support\TourismCatalog::
     * municipalities() remains as static map-display data (name + pin
     * coordinates) for the Explore page and is unaffected by this table.
     */
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('province')->default('Davao Oriental');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
