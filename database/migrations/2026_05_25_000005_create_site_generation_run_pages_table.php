<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('site_generation_run_pages');

        Schema::create('site_generation_run_pages', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('site_generation_run_id', 32);
            $table->string('target_page_id', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name', 160);
            $table->string('slug', 160);
            $table->text('brief');
            $table->string('source', 40)->default('planner');
            $table->enum('status', ['queued', 'generating', 'completed', 'failed', 'skipped'])->default('queued');
            $table->string('error_message', 1000)->nullable();
            $table->timestamps();

            $table->index(['site_generation_run_id', 'sort_order'], 'sgrp_run_order_idx');
            $table->index('target_page_id', 'sgrp_target_page_idx');
            $table->foreign('site_generation_run_id', 'sgrp_run_fk')->references('id')->on('site_generation_runs')->cascadeOnDelete();
            $table->foreign('target_page_id', 'sgrp_target_page_fk')->references('id')->on('pages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_generation_run_pages');
    }
};
