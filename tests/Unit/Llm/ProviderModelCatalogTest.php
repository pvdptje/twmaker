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
                'modalities' => ['text'],
            ],
        ], $catalog->refresh('anthropic', 'test-key'));

        $this->assertSame('claude-new-20260521', $catalog->models('anthropic', 'test-key')[0]['id']);
        Http::assertSentCount(1);
    }

    public function test_marks_claude_3_models_as_vision_capable(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.anthropic.com/v1/models*' => Http::response([
                'data' => [
                    ['id' => 'claude-3-5-sonnet-latest', 'display_name' => 'Claude 3.5 Sonnet'],
                ],
                'has_more' => false,
            ]),
        ]);

        $models = app(ProviderModelCatalog::class)->refresh('anthropic', 'test-key');

        $this->assertContains('image', $models[0]['modalities']);
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
                'modalities' => ['text'],
            ],
        ], $catalog->models('anthropic', 'test-key'));
    }

    public function test_fetches_deepseek_models_and_caches_them(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.deepseek.com/v1/models' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'deepseek-chat',
                        'object' => 'model',
                        'owned_by' => 'deepseek',
                    ],
                ],
            ]),
        ]);

        $catalog = app(ProviderModelCatalog::class);

        $this->assertSame([
            [
                'id' => 'deepseek-chat',
                'label' => 'DeepSeek Chat',
                'modalities' => ['text'],
            ],
        ], $catalog->refresh('deepseek', 'test-key'));

        $this->assertSame('deepseek-chat', $catalog->models('deepseek', 'test-key')[0]['id']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-key'));
    }
}
