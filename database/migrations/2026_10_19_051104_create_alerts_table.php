<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('campaign_key');
            $table->string('type');
            $table->string('status')->default('open');
            $table->json('context');
            $table->timestamp('triggered_at');
            $table->timestamp('last_evaluated_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->index(['workspace_id', 'status', 'triggered_at']);
        });

        // At most one open alert per subject and type, enforced by the database. Partial indexes are supported by both SQLite and PostgreSQL.
        DB::statement("create unique index alerts_one_open_per_subject on alerts (site_id, campaign_key, type) where status = 'open'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
