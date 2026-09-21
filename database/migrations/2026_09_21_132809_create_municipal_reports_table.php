<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consolidated tourism reports an LGU Tourism Admin submits for a
     * municipality, reviewed and approved/returned by the PTO. There is no
     * real `municipalities` table yet (municipality names live as plain
     * strings on `users.organization_subtitle` and `listings.municipality`
     * — see TourismCatalog::municipalities()), so `municipality` follows
     * that same existing convention rather than introducing the first FK
     * to a table that doesn't exist.
     */
    public function up(): void
    {
        Schema::create('municipal_reports', function (Blueprint $table) {
            $table->id();
            $table->string('municipality');
            $table->foreignId('submitted_by')->constrained('users');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('total_arrivals')->default(0);
            $table->string('status')->default('SUBMITTED');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['municipality', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_reports');
    }
};
