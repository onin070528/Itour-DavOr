<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The scheduled archived-image purge job (sub-stage 7D) has no acting
     * user — it needs to write an operation_logs row the same way a failed
     * login before credentials resolve already does on security_logs.
     */
    public function up(): void
    {
        Schema::table('operation_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('operation_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
