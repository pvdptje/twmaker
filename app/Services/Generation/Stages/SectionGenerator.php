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

    public function generate(Page $page, array $plan): array
    {
        $response = $this->provider->sendStructured(new StructuredRequest(
            stage: 'section_generator',
            model: (string) config('llm.providers.anthropic.models.section_generator'),
            systemPrompt: $this->prompts->system('section_generator'),
            userPrompt: $page->prompt,
            toolName: 'submit_raw_html_document',
            schema: $this->schema(),
            context: [
                'page_id' => $page->id,
                'plan' => $plan,
            ],
            maxTokens: (int) config('llm.providers.anthropic.section_max_tokens', 8000),
            temperature: 0.7,
        ));

        return $response->output;
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
