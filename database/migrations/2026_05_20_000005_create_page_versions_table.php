<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_versions', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('page_id', 32);
            $table->json('document_json');
            $table->string('created_by_kind', 40);
            $table->timestampTz('created_at');

            $table->index(['page_id', 'created_at']);
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_versions');
    }
};
