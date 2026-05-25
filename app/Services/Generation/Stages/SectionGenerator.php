<?php

namespace App\Services\Generation\Stages;

use App\Events\GenerationStreamChunk;
use App\Models\Page;
use App\Services\Generation\GenerationStreamBuffer;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\TextRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class SectionGenerator
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly PromptBuilder $prompts,
        private readonly GenerationStreamBuffer $streamBuffer,
    ) {}

    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    public function generate(Page $page, ?string $provider = null, ?string $model = null, ?string $apiKey = null, array $images = [], ?string $promptOverride = null): SectionGenerationResult
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $prompt = $promptOverride ?? $page->prompt;
        $result = $this->send($page, 'section_generator', $this->prompts->system('section_generator'), $this->buildUserPrompt($prompt, $images), $provider, $model, $apiKey, $images);

        if (trim($result->html) !== '') {
            return $result;
        }

        $result = $this->send(
            $page,
            'section_generator_retry',
            $this->prompts->system('section_generator')."\n\nCritical recovery instruction: the previous response returned empty HTML. Return one complete standalone HTML document with balanced tw:block markers. Do not leave the response blank.",
            $this->buildUserPrompt("Generate the complete marked Tailwind HTML document now. Original prompt: {$prompt}", $images),
            $provider,
            $model,
            $apiKey,
            $images,
        );

        if (trim($result->html) !== '') {
            return new SectionGenerationResult($result->html, 'retry', $result->llm);
        }

        return new SectionGenerationResult($this->fallbackHtml($page), 'deterministic_fallback');
    }

    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    private function send(Page $page, string $stage, string $systemPrompt, string $userPrompt, string $provider, ?string $model, ?string $apiKey, array $images = []): SectionGenerationResult
    {
        $this->streamBuffer->resetRun($page->id, $stage);

        $request = new TextRequest(
            stage: $stage,
            provider: $provider,
            model: $model ?: (string) config("llm.providers.{$provider}.models.section_generator"),
            systemPrompt: $systemPrompt."\n\nStreaming instruction: return the complete HTML document directly as plain text. Do not use JSON, markdown, or code fences.",
            userPrompt: $userPrompt,
            context: [
                'page_id' => $page->id,
                'page_name' => $page->name,
                'reference_images' => count($images),
            ],
            maxTokens: (int) config("llm.providers.{$provider}.section_max_tokens", 8000),
            apiKey: $apiKey,
            images: $images,
        );

        try {
            if (! method_exists($this->provider, 'sendTextStream')) {
                throw new \RuntimeException('The configured LLM provider does not support plain text streaming.');
            }

            $response = $this->provider->sendTextStream($request, $this->streamHtml($page, $stage));
        } finally {
            $this->streamBuffer->flushRun($page->id, $stage);
        }

        return new SectionGenerationResult($response->text, llm: [
            'provider' => $provider,
            'model' => $response->model,
            'usage' => $response->usage,
        ]);
    }

    private function streamHtml(Page $page, string $stage): callable
    {
        return function (string $chunk, int $position) use ($page, $stage): void {
            if ($chunk === '') {
                return;
            }

            $this->streamBuffer->append($page->id, $stage, $chunk, $position);

            $this->broadcastChunk(new GenerationStreamChunk($page->id, $stage, $chunk, $position), $page);
        };
    }

    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    private function buildUserPrompt(string $prompt, array $images): string
    {
        if ($images === []) {
            return $prompt;
        }

        $imageNote = 'A visual reference '.(count($images) === 1 ? 'screenshot is' : count($images).' screenshots are')
            .' attached. Use the screenshot to inform the layout, hierarchy, spacing, color, and visual style while honoring the written prompt. Adapt the design into a complete responsive Tailwind HTML page.';

        return trim($prompt."\n\n".$imageNote);
    }

    private function broadcastChunk(GenerationStreamChunk $event, Page $page): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            Log::warning('Generation stream broadcast failed.', [
                'page_id' => $page->id,
                'stage' => $event->stage,
                'stream' => $event->stream,
                'position' => $event->position,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function fallbackHtml(Page $page): string
    {
        $title = $page->name !== '' ? $page->name : 'Generated page';
        $goal = $page->prompt !== '' ? str($page->prompt)->limit(180)->toString() : 'Generated from prompt.';
        $audience = 'Visitors';
        $title = $this->escape($title);
        $goal = $this->escape($goal);
        $audience = $this->escape($audience);

        return <<<HTML
<header class="bg-neutral-950 px-6 py-6 text-white">
  <nav class="mx-auto flex max-w-6xl items-center justify-between gap-6">
    <div class="text-xl font-bold">{$title}</div>
    <a href="#contact" class="rounded-lg bg-cyan-400 px-4 py-2 text-sm font-semibold text-neutral-950">Contact</a>
  </nav>
</header>
<main>
  <section class="bg-neutral-950 px-6 py-24 text-white">
    <div class="mx-auto max-w-5xl">
      <p class="text-sm font-semibold uppercase tracking-wide text-cyan-300">AI recovery draft</p>
      <h1 class="mt-4 text-4xl font-bold leading-tight md:text-6xl">{$title}</h1>
      <p class="mt-6 max-w-3xl text-lg text-neutral-200">{$goal}</p>
      <p class="mt-4 max-w-2xl text-sm text-neutral-400">Built for {$audience}.</p>
    </div>
  </section>

  <section class="bg-white px-6 py-16 text-neutral-950">
    <div class="mx-auto grid max-w-5xl gap-6 md:grid-cols-3">
      <article class="rounded-xl border border-neutral-200 p-6 shadow-sm">
        <h2 class="text-xl font-bold">Clear structure</h2>
        <p class="mt-3 text-neutral-600">A readable recovery layout keeps the page editable.</p>
      </article>
      <article class="rounded-xl border border-neutral-200 p-6 shadow-sm">
        <h2 class="text-xl font-bold">Responsive sections</h2>
        <p class="mt-3 text-neutral-600">The draft uses simple Tailwind regions that work across viewports.</p>
      </article>
      <article class="rounded-xl border border-neutral-200 p-6 shadow-sm">
        <h2 class="text-xl font-bold">Ready to refine</h2>
        <p class="mt-3 text-neutral-600">Use targeted edits to replace any section with a stronger design.</p>
      </article>
    </div>
  </section>
</main>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
