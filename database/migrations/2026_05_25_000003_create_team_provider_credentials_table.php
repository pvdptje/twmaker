<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_provider_credentials', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('team_id', 32);
            $table->string('provider', 80);
            $table->text('api_key')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'provider']);
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_provider_credentials');
    }
};
