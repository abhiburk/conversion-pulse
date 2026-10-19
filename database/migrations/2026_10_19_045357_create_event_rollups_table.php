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
        Schema::create('event_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('campaign_key');
            $table->string('type');
            $table->timestamp('bucket_start');
            $table->unsignedInteger('count');

            $table->unique(['site_id', 'campaign_key', 'type', 'bucket_start']);
            $table->index(['workspace_id', 'bucket_start']);
            $table->index(['campaign_id', 'bucket_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_rollups');
    }
};
