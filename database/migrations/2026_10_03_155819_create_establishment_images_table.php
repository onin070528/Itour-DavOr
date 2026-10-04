<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Create tblestablishment_images, the establishment photo upload/approval workflow.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `listing_id` — not `est_id` — is the real FK: the establishment table
     * this references is the existing `listings` table (App\Models\Listing,
     * never renamed to tblestablishments; only brand-new tables use the
     * ITD tbl/prefix scheme). A4's "FK keeps the parent's key name" doesn't
     * apply here since the parent's own key is plain `id`, not `est_id`.
     *
     * img_replaces_id is a self-reference for the Replace workflow — the
     * new row stays PENDING while the old row it targets stays PUBLISHED
     * until approval (see Establishment\ImagesController::replace()).
     *
     * The partial unique index enforces "the public must never see zero or
     * two covers" at the database level, not just in application code: at
     * most one PUBLISHED, is_cover row per establishment, ever.
     */
    public function up(): void
    {
        Schema::create('tblestablishment_images', function (Blueprint $table) {
            $table->id('img_id');
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('img_path');
            $table->string('img_thumbnail_path');
            $table->string('img_alt_text')->nullable();
            $table->string('img_credit')->nullable();
            $table->string('img_source_role');
            $table->string('img_status');
            $table->boolean('img_is_cover')->default(false);
            $table->unsignedSmallInteger('img_sort_order')->default(0);
            $table->char('img_hash', 64);
            $table->string('img_review_note')->nullable();
            $table->foreignId('img_uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('img_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('img_reviewed_at')->nullable();
            $table->foreignId('img_replaces_id')->nullable()
                ->constrained('tblestablishment_images', 'img_id')->nullOnDelete();
            // No default: the CHECK constraint below requires every insert
            // to explicitly declare true — there is no valid "false" row.
            $table->boolean('img_has_ownership_declared');
            $table->timestamp('img_archived_at')->nullable();
            $table->timestamp('img_created_at')->nullable();
            $table->timestamp('img_updated_at')->nullable();

            $table->index('listing_id');
            $table->index('img_status');
            $table->index('img_hash');
        });

        // Postgres-only: SQLite (the test suite's driver) can't ADD
        // CONSTRAINT/partial-index via ALTER TABLE at all, so these two
        // DB-level guarantees only apply on the app's real database —
        // application-layer validation (Establishment\ImagesController,
        // a later sub-stage) still enforces both rules on every driver.
        if (DB::getDriverName() === 'pgsql') {
            // Ownership must be declared before a row can exist at all —
            // not just defaulted — so this is enforced at the database
            // layer too.
            DB::statement('ALTER TABLE tblestablishment_images ADD CONSTRAINT chk_img_ownership_declared CHECK (img_has_ownership_declared = true)');

            // Partial unique index (Postgres-only syntax — no portable
            // Schema builder equivalent): at most one PUBLISHED cover per
            // establishment, enforced regardless of application-layer bugs.
            DB::statement('CREATE UNIQUE INDEX uq_img_one_published_cover_per_listing ON tblestablishment_images (listing_id) WHERE img_is_cover = true AND img_status = \'PUBLISHED\'');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tblestablishment_images');
    }
};
