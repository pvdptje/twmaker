<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reusable_elements', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('project_id', 32);
            $table->string('name', 120);
            $table->string('type', 40);
            $table->json('default_props');
            $table->text('preview_html_cache')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'type']);
            $table->index('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reusable_elements');
    }
};
