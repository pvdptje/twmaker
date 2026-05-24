<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('page_versions')) {
            return;
        }

        Schema::table('page_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('page_versions', 'summary')) {
                $table->string('summary', 300)->nullable()->after('created_by_kind');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('page_versions')) {
            return;
        }

        Schema::table('page_versions', function (Blueprint $table): void {
            if (Schema::hasColumn('page_versions', 'summary')) {
                $table->dropColumn('summary');
            }
        });
    }
};
