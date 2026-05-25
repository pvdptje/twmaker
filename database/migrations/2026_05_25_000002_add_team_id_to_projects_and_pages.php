<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('team_id', 32)->nullable()->after('id')->index();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->string('team_id', 32)->nullable()->after('project_id')->index();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        $firstTeamId = DB::table('users')
            ->whereNotNull('default_team_id')
            ->orderBy('id')
            ->value('default_team_id');

        if (is_string($firstTeamId) && $firstTeamId !== '') {
            DB::table('projects')
                ->whereNull('team_id')
                ->update(['team_id' => $firstTeamId]);
        }

        DB::table('projects')
            ->whereNotNull('team_id')
            ->orderBy('id')
            ->get(['id', 'team_id'])
            ->each(function (object $project): void {
                DB::table('pages')
                    ->where('project_id', $project->id)
                    ->whereNull('team_id')
                    ->update(['team_id' => $project->team_id]);
            });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};
