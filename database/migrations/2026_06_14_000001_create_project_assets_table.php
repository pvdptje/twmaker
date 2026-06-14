<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_assets', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('project_id', 32);
            $table->string('team_id', 32)->nullable()->index();
            $table->string('original_name', 255);
            $table->string('mime_type', 64);
            $table->string('disk', 64)->default('public');
            $table->string('path', 512);
            $table->string('public_url', 512);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('bytes');
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_assets');
    }
};
