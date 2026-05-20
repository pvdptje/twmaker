<?php

namespace App\Services\Generation\Stages;

use App\Models\Page;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\StructuredRequest;

class HtmlMarker
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly PromptBuilder $prompts,
    ) {}

    public function mark(Page $page, array $plan, array $artifact): array
    {
        $response = $this->provider->sendStructured(new StructuredRequest(
            stage: 'html_marker',
            model: (string) config('llm.providers.anthropic.models.repair'),
            systemPrompt: $this->prompts->system('html_marker'),
            userPrompt: $page->prompt,
            toolName: 'submit_marked_html_document',
            schema: $this->schema(),
            context: [
                'page_id' => $page->id,
                'plan' => $plan,
                'title' => $artifact['title'] ?? $page->name,
                'page_type' => $artifact['page_type'] ?? 'generic',
                'goal' => $artifact['goal'] ?? '',
                'audience' => $artifact['audience'] ?? '',
                'prompt_summary' => $artifact['prompt_summary'] ?? $page->prompt,
                'raw_html' => $artifact['raw_html'] ?? $artifact['html_source'] ?? '',
            ],
            maxTokens: (int) config('llm.providers.anthropic.marker_max_tokens', 8000),
            temperature: 0.2,
        ));

        return $response->output;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['html_source'],
            'properties' => [
                'html_source' => ['type' => 'string', 'minLength' => 1],
            ],
        ];
    }
}
