<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Destinations and tourism establishments, in one unified shape —
     * replaces the static App\Support\TourismCatalog::listings() array as
     * the source of truth. `slug` carries the same human-readable ids the
     * mock data used (e.g. "dahican-beach"), since routes/lgu.establishmentQr
     * and dashboard modal ids are already built around them.
     */
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('municipality');
            $table->string('barangay');
            $table->text('description')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->json('tags')->nullable();
            $table->string('image')->nullable();
            $table->string('contact_office')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('hours')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();

            $table->index(['category', 'municipality']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
