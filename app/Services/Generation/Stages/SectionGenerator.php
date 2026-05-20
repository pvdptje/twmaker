<?php

namespace App\Services\Generation\Stages;

use App\Models\Page;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\StructuredRequest;
use App\Services\Schema\DocumentSchema;

class SectionGenerator
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly PromptBuilder $prompts,
    ) {}

    public function generate(Page $page, array $plan, array $libraryDigest): array
    {
        $response = $this->provider->sendStructured(new StructuredRequest(
            stage: 'section_generator',
            model: (string) config('llm.providers.anthropic.models.section_generator'),
            systemPrompt: $this->prompts->system('section_generator'),
            userPrompt: $page->prompt,
            toolName: 'submit_document',
            schema: DocumentSchema::schema(),
            context: [
                'page_id' => $page->id,
                'plan' => $plan,
                'project_library' => $libraryDigest,
            ],
            maxTokens: 12000,
        ));

        return $response->output;
    }
}
