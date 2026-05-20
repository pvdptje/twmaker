<?php

namespace Tests\Feature\Generation;

use App\Models\Page;
use App\Models\Project;
use App\Models\ReusableElement;
use App\Services\Generation\Pipeline;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\StructuredRequest;
use App\Services\Llm\StructuredResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_persists_valid_document_and_generation_events(): void
    {
        $ids = app(IdGenerator::class);
        $project = Project::query()->create([
            'id' => $ids->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => $ids->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => 'A developer tool landing page',
            'document_json' => $this->emptyDocument(),
            'status' => 'draft',
        ]);

        $this->app->instance(LlmProvider::class, new FakeGenerationProvider($this->generatedDocument()));

        $document = app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $this->assertSame($document, $page->document_json);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'stage_completed',
            'stage' => 'planner',
            'level' => 'success',
        ]);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'generation_completed',
            'stage' => 'validation',
            'level' => 'success',
        ]);
    }

    public function test_pipeline_repairs_malformed_section_column_counts(): void
    {
        $ids = app(IdGenerator::class);
        $project = Project::query()->create([
            'id' => $ids->project(),
            'name' => 'Acme',
        ]);
        ReusableElement::query()->create([
            'id' => 'elem_01h00000000000000000000001',
            'project_id' => $project->id,
            'name' => 'Footer links',
            'type' => 'nav_link_group',
            'default_props' => [
                'links' => [['label' => 'Home', 'href' => '#', 'active' => true]],
                'layout' => 'horizontal',
            ],
        ]);
        $page = Page::query()->create([
            'id' => $ids->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => 'A developer tool landing page',
            'document_json' => $this->emptyDocument(),
            'status' => 'draft',
        ]);
        $document = $this->generatedDocument();
        $document['document_tree'][] = $this->footerWithMalformedColumns();

        $this->app->instance(LlmProvider::class, new FakeGenerationProvider($document));

        app(Pipeline::class)->generate($page);

        $page->refresh();
        $this->assertSame('valid', $page->status);
        $this->assertSame(1, $page->document_json['document_tree'][1]['props']['columns']);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'repair_attempt',
            'stage' => 'repair',
        ]);
    }

    private function emptyDocument(): array
    {
        $document = $this->generatedDocument();
        $document['page_metadata']['status'] = 'draft';
        $document['document_tree'] = [];

        return $document;
    }

    private function generatedDocument(): array
    {
        $now = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return [
            'schema_version' => 1,
            'page_metadata' => [
                'title' => 'Acme DevTools',
                'page_type' => 'landing',
                'goal' => 'Convince developers to try Acme.',
                'audience' => 'Senior backend engineers',
                'prompt_summary' => 'Developer tool landing page',
                'status' => 'valid',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'design_system' => [
                'colors' => [
                    'primary' => 'cyan',
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
                    'section_padding' => 'md',
                ],
                'radius' => 'md',
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
                        [
                            'id' => 'node_01h00000000000000000000001',
                            'type' => 'heading',
                            'props' => [
                                'level' => 1,
                                'text' => 'Ship pages with structure',
                                'alignment' => 'center',
                                'emphasis' => 'default',
                            ],
                            'locks' => $this->locks(),
                            'metadata' => $this->metadata(),
                        ],
                        [
                            'id' => 'node_01h00000000000000000000002',
                            'type' => 'text',
                            'props' => [
                                'text' => 'Turn prompts into validated landing page JSON.',
                                'size' => 'lg',
                                'alignment' => 'center',
                                'emphasis' => 'muted',
                            ],
                            'locks' => $this->locks(),
                            'metadata' => $this->metadata(),
                        ],
                    ],
                    'locks' => $this->locks(),
                    'metadata' => $this->metadata(),
                ],
            ],
            'generation_history' => [],
        ];
    }

    private function footerWithMalformedColumns(): array
    {
        return [
            'id' => 'sec_01h00000000000000000000002',
            'type' => 'footer',
            'props' => [
                'background' => 'default',
                'padding' => 'lg',
                'max_width' => 'default',
                'alignment' => 'left',
                'variant' => 'simple',
                'columns' => ['Main'],
            ],
            'children' => [
                [
                    'id' => 'node_01h00000000000000000000003',
                    'type' => 'image',
                    'props' => [
                        'src' => 'placeholder:logo',
                        'alt' => 'Acme',
                        'width' => null,
                        'height' => null,
                        'fit' => 'contain',
                        'radius' => 'none',
                    ],
                    'locks' => $this->locks(),
                    'metadata' => $this->metadata(),
                ],
                [
                    'id' => 'inst_01h00000000000000000000001',
                    'type' => 'element_instance',
                    'props' => [
                        'library_id' => 'elem_01h00000000000000000000001',
                        'overrides' => [],
                    ],
                    'locks' => $this->locks(),
                    'metadata' => $this->metadata(),
                ],
            ],
            'locks' => $this->locks(),
            'metadata' => $this->metadata(),
        ];
    }

    private function locks(): array
    {
        return ['content_locked' => false, 'style_locked' => false, 'layout_locked' => false];
    }

    private function metadata(): array
    {
        return ['created_by' => 'generator', 'created_at' => '2026-05-20T18:00:00Z', 'updated_at' => '2026-05-20T18:00:00Z'];
    }
}

class FakeGenerationProvider implements LlmProvider
{
    public function __construct(private readonly array $document) {}

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $output = $request->stage === 'planner'
            ? [
                'title' => 'Acme DevTools',
                'page_type' => 'landing',
                'goal' => 'Convince developers to try Acme.',
                'audience' => 'Senior backend engineers',
                'prompt_summary' => 'Developer tool landing page',
                'sections' => [['type' => 'hero', 'intent' => 'Introduce the product.']],
            ]
            : $this->document;

        return new StructuredResponse($request->stage, $request->model, $output);
    }
}
