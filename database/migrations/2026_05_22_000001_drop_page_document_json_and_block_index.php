<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pages', 'document_json') && Schema::hasColumn('pages', 'html_source')) {
            DB::table('pages')
                ->where(function ($query): void {
                    $query->whereNull('html_source')->orWhere('html_source', '');
                })
                ->select(['id', 'document_json'])
                ->chunkById(100, function ($pages): void {
                    foreach ($pages as $page) {
                        $document = json_decode((string) $page->document_json, true);
                        $html = is_array($document) ? ($document['html_source'] ?? null) : null;

                        if (is_string($html) && trim($html) !== '') {
                            DB::table('pages')->where('id', $page->id)->update(['html_source' => $html]);
                        }
                    }
                }, 'id');
        }

        Schema::table('pages', function (Blueprint $table): void {
            if (Schema::hasColumn('pages', 'block_index')) {
                $table->dropColumn('block_index');
            }
        });

        Schema::table('pages', function (Blueprint $table): void {
            if (Schema::hasColumn('pages', 'document_json')) {
                $table->dropColumn('document_json');
            }
        });

        if (Schema::hasTable('page_versions')) {
            Schema::table('page_versions', function (Blueprint $table): void {
                if (! Schema::hasColumn('page_versions', 'html_source')) {
                    $table->longText('html_source')->nullable()->after('page_id');
                }
            });

            if (Schema::hasColumn('page_versions', 'document_json')) {
                DB::table('page_versions')
                    ->where(function ($query): void {
                        $query->whereNull('html_source')->orWhere('html_source', '');
                    })
                    ->select(['id', 'document_json'])
                    ->chunkById(100, function ($versions): void {
                        foreach ($versions as $version) {
                            $document = json_decode((string) $version->document_json, true);
                            $html = is_array($document) ? ($document['html_source'] ?? null) : null;

                            if (is_string($html) && trim($html) !== '') {
                                DB::table('page_versions')->where('id', $version->id)->update(['html_source' => $html]);
                            }
                        }
                    }, 'id');

                Schema::table('page_versions', function (Blueprint $table): void {
                    $table->dropColumn('document_json');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('pages', 'document_json')) {
                $table->json('document_json')->nullable()->after('prompt');
            }

            if (! Schema::hasColumn('pages', 'block_index')) {
                $table->json('block_index')->nullable()->after('html_source');
            }
        });

        if (Schema::hasTable('page_versions')) {
            Schema::table('page_versions', function (Blueprint $table): void {
                if (! Schema::hasColumn('page_versions', 'document_json')) {
                    $table->json('document_json')->nullable()->after('page_id');
                }

                if (Schema::hasColumn('page_versions', 'html_source')) {
                    $table->dropColumn('html_source');
                }
            });
        }
    }
};
