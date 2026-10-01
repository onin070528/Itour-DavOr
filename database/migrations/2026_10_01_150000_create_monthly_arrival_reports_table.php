<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per establishment (listings.id) per calendar month — the
     * consolidation unit the Digital and Manual/Paper submission paths both
     * write into, distinguished only by `submission_source`, so LGU review,
     * verification, and consolidation into municipal_reports never has to
     * branch on how a report arrived. `period_month` is always normalized
     * to the 1st of the month (a calendar month is fully determined by one
     * date). `municipality_id` is denormalized from the listing at write
     * time for cheap LGU-dashboard filtering, the same pattern already used
     * by operation_logs. "Not Submitted" is deliberately not a `status`
     * value — it's the absence of a row for a given (listing_id,
     * period_month), computed by the LGU dashboard, never persisted, so a
     * missing report can never be silently treated as a zero-arrival one.
     */
    public function up(): void
    {
        Schema::create('monthly_arrival_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->restrictOnDelete();
            $table->date('period_month');

            $table->string('submission_source');
            $table->string('status')->default('ForReview');

            $table->unsignedInteger('party_male')->default(0);
            $table->unsignedInteger('party_female')->default(0);
            $table->unsignedInteger('party_adults')->default(0);
            $table->unsignedInteger('party_children')->default(0);
            $table->unsignedInteger('party_seniors')->default(0);
            $table->unsignedInteger('party_local')->default(0);
            $table->unsignedInteger('party_foreign')->default(0);
            $table->unsignedInteger('total_visitors')->default(0);

            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamp('submitted_at');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('municipal_report_id')->nullable()->constrained('municipal_reports')->nullOnDelete();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(['listing_id', 'period_month']);
            $table->index(['municipality_id', 'period_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_arrival_reports');
    }
};
