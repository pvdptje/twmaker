<?php

namespace App\Services\Generation\Stages;

use App\Models\Page;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\StructuredRequest;

class Planner
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly PromptBuilder $prompts,
    ) {}

    public function plan(Page $page): array
    {
        $response = $this->provider->sendStructured(new StructuredRequest(
            stage: 'planner',
            model: (string) config('llm.providers.anthropic.models.planner'),
            systemPrompt: $this->prompts->system('planner'),
            userPrompt: $page->prompt,
            toolName: 'submit_page_plan',
            schema: $this->schema(),
            context: [
                'page_id' => $page->id,
            ],
        ));

        return $response->output;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'page_type', 'goal', 'audience', 'prompt_summary', 'sections'],
            'properties' => [
                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                'page_type' => ['type' => 'string'],
                'goal' => ['type' => 'string'],
                'audience' => ['type' => 'string'],
                'prompt_summary' => ['type' => 'string'],
                'sections' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'intent'],
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'intent' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
