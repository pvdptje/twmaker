<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('site_generation_run_pages');
        Schema::dropIfExists('site_generation_runs');

        Schema::create('site_generation_runs', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('team_id', 32);
            $table->string('project_id', 32);
            $table->string('source_page_id', 32);
            $table->enum('status', ['draft', 'queued', 'running', 'completed', 'failed'])->default('draft');
            $table->string('provider', 80)->nullable();
            $table->string('model', 160)->nullable();
            $table->json('planned_pages')->nullable();
            $table->json('generated_page_ids')->nullable();
            $table->string('zip_disk', 80)->default('local');
            $table->string('zip_path')->nullable();
            $table->string('zip_filename')->nullable();
            $table->unsignedBigInteger('zip_byte_size')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'source_page_id'], 'sgr_project_source_idx');
            $table->index(['status', 'created_at'], 'sgr_status_created_idx');
            $table->foreign('team_id', 'sgr_team_fk')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('project_id', 'sgr_project_fk')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('source_page_id', 'sgr_source_page_fk')->references('id')->on('pages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_generation_runs');
    }
};
