<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_events', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('page_id', 32);
            $table->string('kind', 60);
            $table->string('stage', 40);
            $table->string('target_id', 32)->nullable();
            $table->string('level', 20);
            $table->string('summary', 500);
            $table->json('payload')->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['page_id', 'occurred_at']);
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_events');
    }
};
