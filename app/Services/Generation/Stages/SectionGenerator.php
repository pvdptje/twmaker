<?php

namespace App\Services\Generation\Stages;

use App\Models\Page;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\StructuredRequest;

class SectionGenerator
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly PromptBuilder $prompts,
    ) {}

    public function generate(Page $page, ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $artifact = $this->send($page, 'section_generator', $this->prompts->system('section_generator'), $page->prompt, 0.7, $provider, $model, $apiKey);

        if ($this->hasRawHtml($artifact)) {
            return $artifact;
        }

        $artifact = $this->send(
            $page,
            'section_generator_retry',
            $this->prompts->system('section_generator')."\n\nCritical recovery instruction: the previous response returned empty HTML. You must fill `raw_html` with complete body HTML containing multiple Tailwind-styled sections. Do not leave `raw_html` blank.",
            "Generate the full Tailwind HTML page now. Original prompt: {$page->prompt}",
            0.4,
            $provider,
            $model,
            $apiKey,
        );

        if ($this->hasRawHtml($artifact)) {
            return ['_recovered' => 'retry'] + $artifact;
        }

        return ['_recovered' => 'deterministic_fallback'] + $this->fallbackArtifact($page);
    }

    private function send(Page $page, string $stage, string $systemPrompt, string $userPrompt, float $temperature, string $provider, ?string $model, ?string $apiKey): array
    {
        $response = $this->provider->sendStructured(new StructuredRequest(
            stage: $stage,
            provider: $provider,
            model: $model ?: (string) config("llm.providers.{$provider}.models.section_generator"),
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            toolName: 'submit_raw_html_document',
            schema: $this->schema(),
            context: [
                'page_id' => $page->id,
                'page_name' => $page->name,
            ],
            maxTokens: (int) config("llm.providers.{$provider}.section_max_tokens", 8000),
            temperature: $temperature,
            apiKey: $apiKey,
        ));

        return $response->output + ['_llm' => [
            'provider' => $provider,
            'model' => $response->model,
            'usage' => $response->usage,
        ]];
    }

    private function hasRawHtml(array $artifact): bool
    {
        foreach ([$artifact['raw_html'] ?? null, $artifact['html_source'] ?? null] as $html) {
            if (is_string($html) && trim($html) !== '') {
                return true;
            }
        }

        return false;
    }

    private function fallbackArtifact(Page $page): array
    {
        $title = $page->name !== '' ? $page->name : 'Generated page';
        $goal = $page->prompt !== '' ? str($page->prompt)->limit(180)->toString() : 'Generated from prompt.';
        $audience = 'Visitors';
        $summary = $page->prompt !== '' ? str($page->prompt)->limit(240)->toString() : 'Generated page';

        return [
            'title' => $title,
            'page_type' => 'landing',
            'goal' => $goal,
            'audience' => $audience,
            'prompt_summary' => $summary,
            'raw_html' => $this->fallbackHtml($title, $goal, $audience),
        ];
    }

    private function fallbackHtml(string $title, string $goal, string $audience): string
    {
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

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'page_type', 'goal', 'audience', 'prompt_summary', 'raw_html'],
            'properties' => [
                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                'page_type' => ['type' => 'string'],
                'goal' => ['type' => 'string'],
                'audience' => ['type' => 'string'],
                'prompt_summary' => ['type' => 'string'],
                'raw_html' => ['type' => 'string', 'minLength' => 1],
            ],
        ];
    }
}
