<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ingested_events', function (Blueprint $table) {
            $table->timestamp('dispatched_at')->nullable()->after('status');
            $table->timestamp('failed_at')->nullable()->after('processed_at');

            $table->index(['status', 'dispatched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingested_events', function (Blueprint $table) {
            $table->dropIndex(['status', 'dispatched_at']);
            $table->dropColumn(['dispatched_at', 'failed_at']);
        });
    }
};
