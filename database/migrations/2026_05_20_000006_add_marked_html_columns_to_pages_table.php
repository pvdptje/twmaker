<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('pages', 'html_source')) {
                $table->longText('html_source')->nullable()->after('document_json');
            }

            if (! Schema::hasColumn('pages', 'block_index')) {
                $table->json('block_index')->nullable()->after('html_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            if (Schema::hasColumn('pages', 'block_index')) {
                $table->dropColumn('block_index');
            }

            if (Schema::hasColumn('pages', 'html_source')) {
                $table->dropColumn('html_source');
            }
        });
    }
};
