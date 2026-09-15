<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per visitor-arrival submission, from either of the app's two
     * arrival forms:
     *  - `staff`: the establishment front-desk "Record Arrival" wizard
     *    (single visitor: gender + classification + optional remarks).
     *  - `self_checkin`: the public QR self-registration form (a party,
     *    described by gender/age/tourist-type counters).
     * The `party_*` columns are only ever set for `self_checkin` rows; the
     * `gender`/`classification` columns only for `staff` rows.
     */
    public function up(): void
    {
        Schema::create('arrivals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->date('date');
            $table->string('visitor_name')->nullable();
            $table->string('visitor_contact')->nullable();
            $table->string('gender')->nullable();
            $table->string('classification')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('party_male')->nullable();
            $table->unsignedInteger('party_female')->nullable();
            $table->unsignedInteger('party_adults')->nullable();
            $table->unsignedInteger('party_children')->nullable();
            $table->unsignedInteger('party_seniors')->nullable();
            $table->unsignedInteger('party_local')->nullable();
            $table->unsignedInteger('party_foreign')->nullable();
            $table->unsignedInteger('party_size')->default(1);
            $table->string('status')->default('Recorded');
            $table->timestamps();

            $table->index(['listing_id', 'source', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arrivals');
    }
};
