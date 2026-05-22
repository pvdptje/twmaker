<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\ProviderModelCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderModelCatalogTest extends TestCase
{
    public function test_fetches_anthropic_models_and_caches_them(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.anthropic.com/v1/models*' => Http::response([
                'data' => [
                    [
                        'id' => 'claude-new-20260521',
                        'display_name' => 'Claude New',
                        'type' => 'model',
                    ],
                ],
                'has_more' => false,
            ]),
        ]);

        $catalog = app(ProviderModelCatalog::class);

        $this->assertNull($catalog->models('anthropic', 'test-key'));

        $this->assertSame([
            [
                'id' => 'claude-new-20260521',
                'label' => 'Claude New',
            ],
        ], $catalog->refresh('anthropic', 'test-key'));

        $this->assertSame('claude-new-20260521', $catalog->models('anthropic', 'test-key')[0]['id']);
        Http::assertSentCount(1);
    }

    public function test_forgets_one_cached_model(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.anthropic.com/v1/models*' => Http::response([
                'data' => [
                    ['id' => 'claude-live-20260521', 'display_name' => 'Claude Live'],
                    ['id' => 'claude-dead-20250101', 'display_name' => 'Claude Dead'],
                ],
                'has_more' => false,
            ]),
        ]);

        $catalog = app(ProviderModelCatalog::class);
        $catalog->refresh('anthropic', 'test-key');
        $catalog->forgetModel('anthropic', 'test-key', 'claude-dead-20250101');

        $this->assertSame([
            [
                'id' => 'claude-live-20260521',
                'label' => 'Claude Live',
            ],
        ], $catalog->models('anthropic', 'test-key'));
    }

    public function test_fetches_deepseek_models_and_caches_them(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.deepseek.com/models' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'deepseek-v4-flash',
                        'object' => 'model',
                        'owned_by' => 'deepseek',
                    ],
                ],
            ]),
        ]);

        $catalog = app(ProviderModelCatalog::class);

        $this->assertSame([
            [
                'id' => 'deepseek-v4-flash',
                'label' => 'DeepSeek V4 Flash',
            ],
        ], $catalog->refresh('deepseek', 'test-key'));

        $this->assertSame('deepseek-v4-flash', $catalog->models('deepseek', 'test-key')[0]['id']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-key'));
    }
}
