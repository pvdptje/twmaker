<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('name', 120);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table): void {
            $table->string('team_id', 32);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['team_id', 'user_id']);
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        DB::table('users')
            ->orderBy('id')
            ->get(['id', 'name', 'default_team_id'])
            ->each(function (object $user): void {
                if ($user->default_team_id !== null) {
                    return;
                }

                $teamId = 'team_'.strtolower((string) Str::ulid());

                DB::table('teams')->insert([
                    'id' => $teamId,
                    'name' => "{$user->name}'s Team",
                    'owner_user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('team_user')->insert([
                    'team_id' => $teamId,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('users')->where('id', $user->id)->update([
                    'default_team_id' => $teamId,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }
};
