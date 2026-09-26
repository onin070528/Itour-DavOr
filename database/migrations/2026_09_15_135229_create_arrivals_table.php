<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per visitor-arrival submission, from either of the app's two
     * arrival forms — both describe a party via the same `party_*`
     * gender/age/tourist-type counters:
     *  - `staff`: the establishment front-desk "Record Arrival" wizard,
     *    used when a guest can't scan the QR.
     *  - `self_checkin`: the public QR self-registration form.
     * `gender`/`classification` are legacy single-visitor columns from an
     * earlier version of the staff wizard — left in place (nullable) for
     * historical rows, no longer written by either form.
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
