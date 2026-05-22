<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\StructuredRequest;
use PHPUnit\Framework\TestCase;

class StructuredRequestTest extends TestCase
{
    public function test_builds_tool_definition_from_schema(): void
    {
        $request = new StructuredRequest(
            stage: 'html_marker',
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            systemPrompt: 'Mark raw HTML.',
            userPrompt: 'A raw HTML page.',
            toolName: 'submit_marked_html_document',
            schema: ['type' => 'object', 'properties' => []],
        );

        $this->assertSame([
            'name' => 'submit_marked_html_document',
            'description' => 'Return the structured html_marker result.',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ], $request->toolDefinition());
    }
}
