<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('pages', 'screenshot_path')) {
                $table->string('screenshot_path')->nullable()->after('rendered_html_cache');
            }

            if (! Schema::hasColumn('pages', 'screenshot_status')) {
                $table->string('screenshot_status')->nullable()->after('screenshot_path');
            }

            if (! Schema::hasColumn('pages', 'screenshot_taken_at')) {
                $table->timestamp('screenshot_taken_at')->nullable()->after('screenshot_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            foreach (['screenshot_taken_at', 'screenshot_status', 'screenshot_path'] as $column) {
                if (Schema::hasColumn('pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
