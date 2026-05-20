<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('project_id', 32);
            $table->string('name', 160);
            $table->text('prompt');
            $table->json('document_json');
            $table->longText('rendered_html_cache')->nullable();
            $table->enum('status', ['draft', 'generating', 'valid', 'invalid', 'error']);
            $table->string('last_generation_summary', 500)->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
