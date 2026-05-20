<?php

namespace Tests\Unit\Rendering;

use App\Services\Rendering\Renderer;
use App\Services\Rendering\TailwindClassMap;
use InvalidArgumentException;
use Tests\TestCase;

class RendererTest extends TestCase
{
    public function test_renders_document_nodes_and_element_instances_with_selection_ids(): void
    {
        $html = app(Renderer::class)->renderPreviewDocument($this->document(), $this->library());

        $this->assertStringContainsString('<link rel="stylesheet" href="/preview.css">', $html);
        $this->assertStringContainsString('<script src="https://cdn.tailwindcss.com"></script>', $html);
        $this->assertStringContainsString('<script src="/preview-bridge.js"></script>', $html);
        $this->assertStringContainsString('data-node-id="sec_01h00000000000000000000000"', $html);
        $this->assertStringContainsString('data-node-id="node_01h00000000000000000000001"', $html);
        $this->assertStringContainsString('data-node-id="inst_01h00000000000000000000001"', $html);
        $this->assertStringContainsString('Ship pages with structure', $html);
    }

    public function test_tailwind_map_rejects_unknown_classes_in_debug_mode(): void
    {
        config(['app.debug' => true]);

        $this->expectException(InvalidArgumentException::class);

        app(TailwindClassMap::class)->classes(['not-in-safelist']);
    }

    private function document(): array
    {
        return [
            'schema_version' => 1,
            'page_metadata' => [
                'title' => 'Acme',
                'page_type' => 'landing',
                'goal' => 'Convince visitors to try Acme.',
                'audience' => 'Developers',
                'prompt_summary' => 'A landing page for Acme.',
                'status' => 'valid',
                'created_at' => '2026-05-20T18:00:00Z',
                'updated_at' => '2026-05-20T18:00:00Z',
            ],
            'design_system' => [
                'colors' => [
                    'primary' => 'blue',
                    'accent' => 'emerald',
                    'neutral' => 'neutral',
                    'background' => 'white',
                    'foreground' => 'neutral-950',
                ],
                'typography' => [
                    'heading_family' => 'sans',
                    'body_family' => 'sans',
                    'scale' => 'comfortable',
                ],
                'spacing' => [
                    'density' => 'comfortable',
                    'section_padding' => 'lg',
                ],
                'radius' => 'lg',
                'tone' => 'technical',
                'dark_mode' => false,
            ],
            'document_tree' => [
                [
                    'id' => 'sec_01h00000000000000000000000',
                    'type' => 'hero',
                    'props' => [
                        'background' => 'default',
                        'padding' => 'lg',
                        'max_width' => 'default',
                        'alignment' => 'center',
                        'variant' => 'centered',
                        'image_url' => null,
                    ],
                    'children' => [
                        $this->node('node_01h00000000000000000000000', 'badge', ['label' => 'Beta', 'tone' => 'accent']),
                        $this->node('node_01h00000000000000000000001', 'heading', ['level' => 1, 'text' => 'Ship pages with structure', 'alignment' => 'center', 'emphasis' => 'default']),
                        $this->node('node_01h00000000000000000000002', 'text', ['text' => 'A renderer turns page JSON into selectable HTML.', 'size' => 'lg', 'alignment' => 'center', 'emphasis' => 'muted']),
                        $this->elementInstance('inst_01h00000000000000000000001', 'elem_01h00000000000000000000001'),
                    ],
                    'locks' => $this->locks(),
                    'metadata' => $this->metadata(),
                ],
            ],
            'generation_history' => [],
        ];
    }

    private function library(): array
    {
        return [
            'elem_01h00000000000000000000001' => [
                'id' => 'elem_01h00000000000000000000001',
                'type' => 'cta_group',
                'default_props' => [
                    'primary' => ['label' => 'Start', 'href' => '#start'],
                    'secondary' => ['label' => 'Docs', 'href' => '#docs'],
                    'alignment' => 'center',
                ],
            ],
        ];
    }

    private function node(string $id, string $type, array $props): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'props' => $props,
            'locks' => $this->locks(),
            'metadata' => $this->metadata(),
        ];
    }

    private function elementInstance(string $id, string $libraryId): array
    {
        return [
            'id' => $id,
            'type' => 'element_instance',
            'props' => ['library_id' => $libraryId, 'overrides' => []],
            'locks' => $this->locks(),
            'metadata' => $this->metadata('library_instance'),
        ];
    }

    private function locks(): array
    {
        return ['content_locked' => false, 'style_locked' => false, 'layout_locked' => false];
    }

    private function metadata(string $createdBy = 'generator'): array
    {
        return ['created_by' => $createdBy, 'created_at' => '2026-05-20T18:00:00Z', 'updated_at' => '2026-05-20T18:00:00Z'];
    }
}
