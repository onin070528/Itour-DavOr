<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * I3: the scheduled purge job clears these two paths once an Archived
     * image's retention period has passed, keeping the row itself.
     */
    public function up(): void
    {
        Schema::table('tblestablishment_images', function (Blueprint $table) {
            $table->string('img_path')->nullable()->change();
            $table->string('img_thumbnail_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tblestablishment_images', function (Blueprint $table) {
            $table->string('img_path')->nullable(false)->change();
            $table->string('img_thumbnail_path')->nullable(false)->change();
        });
    }
};
