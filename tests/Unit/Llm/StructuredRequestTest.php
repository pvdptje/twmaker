<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\StructuredRequest;
use PHPUnit\Framework\TestCase;

class StructuredRequestTest extends TestCase
{
    public function test_builds_tool_definition_from_schema(): void
    {
        $request = new StructuredRequest(
            stage: 'planner',
            model: 'claude-sonnet-4-5',
            systemPrompt: 'Plan with V1 vocabulary.',
            userPrompt: 'Build a page.',
            toolName: 'submit_page_plan',
            schema: ['type' => 'object', 'properties' => []],
        );

        $this->assertSame([
            'name' => 'submit_page_plan',
            'description' => 'Return the structured planner result.',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ], $request->toolDefinition());
    }
}
