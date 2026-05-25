<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\StructuredRequest;
use App\Services\Llm\TextRequest;
use Tests\TestCase;

class PrismProviderTest extends TestCase
{
    public function test_text_requests_do_not_expose_temperature_setting(): void
    {
        $request = new TextRequest(
            stage: 'section_generator',
            provider: 'openai',
            model: 'gpt-4.1-mini',
            systemPrompt: 'System',
            userPrompt: 'User',
        );

        $this->assertObjectNotHasProperty('temperature', $request);
    }

    public function test_structured_requests_do_not_expose_temperature_setting(): void
    {
        $request = new StructuredRequest(
            stage: 'html_marker',
            provider: 'openai',
            model: 'gpt-4.1-mini',
            systemPrompt: 'System',
            userPrompt: 'User',
            toolName: 'submit_marked_html_document',
            schema: ['type' => 'object', 'properties' => []],
        );

        $this->assertObjectNotHasProperty('temperature', $request);
    }
}
