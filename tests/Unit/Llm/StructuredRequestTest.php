<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\StructuredRequest;
use PHPUnit\Framework\TestCase;

class StructuredRequestTest extends TestCase
{
    public function test_builds_tool_definition_from_schema(): void
    {
        $request = new StructuredRequest(
            stage: 'section_generator',
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            systemPrompt: 'Generate raw HTML.',
            userPrompt: 'Build a page.',
            toolName: 'submit_raw_html_document',
            schema: ['type' => 'object', 'properties' => []],
        );

        $this->assertSame([
            'name' => 'submit_raw_html_document',
            'description' => 'Return the structured section_generator result.',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ], $request->toolDefinition());
    }
}
