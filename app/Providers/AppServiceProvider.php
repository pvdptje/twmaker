<?php

namespace App\Providers;

use App\Services\Llm\AnthropicProvider;
use App\Services\Llm\LlmProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmProvider::class, fn (): LlmProvider => match (config('llm.default_provider')) {
            'anthropic' => new AnthropicProvider,
            default => new AnthropicProvider,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
