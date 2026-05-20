<?php

namespace Tests\Feature;

use App\Jobs\GeneratePageJob;
use App\Livewire\Builder\SidePanels\GenerationControls\GenerationControls;
use App\Livewire\Builder\StreamPanel\StreamPanel;
use App\Livewire\Builder\Workspace\Workspace;
use App\Livewire\Projects\ProjectDashboard\ProjectDashboard;
use App\Livewire\Projects\ProjectList\ProjectList;
use App\Models\Page;
use App\Models\Project;
use App\Models\ReusableElement;
use App\Services\Ids\IdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BuilderShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_list_creates_a_project(): void
    {
        Livewire::test(ProjectList::class)
            ->set('name', 'Acme Launches')
            ->set('description', 'Internal landing pages')
            ->call('createProject')
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Acme Launches',
            'description' => 'Internal landing pages',
        ]);
    }

    public function test_project_dashboard_creates_an_empty_page_and_redirects_to_workspace(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);

        Livewire::test(ProjectDashboard::class, ['project' => $project])
            ->set('name', 'Homepage')
            ->set('prompt', 'A developer-tool landing page')
            ->call('createPage')
            ->assertRedirect();

        $page = Page::query()->firstOrFail();

        $this->assertSame($project->id, $page->project_id);
        $this->assertSame('draft', $page->status);
        $this->assertSame([], $page->document_json['document_tree']);
    }

    public function test_workspace_renders_four_panel_shell_with_placeholder_canvas_and_empty_stream(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);

        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'document_json' => $this->emptyDocument(),
            'status' => 'draft',
        ]);

        $this->get(route('builder.workspace', [$project, $page]))
            ->assertOk()
            ->assertSee('Canvas')
            ->assertSee('Inspector')
            ->assertSee('Stream')
            ->assertSee('No generation events yet.')
            ->assertSee('maxRows: 80', false)
            ->assertSee('/preview.css', false);
    }

    public function test_workspace_renders_handcrafted_document_in_preview_srcdoc(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);

        ReusableElement::query()->create([
            'id' => 'elem_01h00000000000000000000001',
            'project_id' => $project->id,
            'name' => 'Hero CTA',
            'type' => 'cta_group',
            'default_props' => [
                'primary' => ['label' => 'Start', 'href' => '#start'],
                'secondary' => null,
                'alignment' => 'center',
            ],
        ]);

        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'document_json' => $this->renderDocument(),
            'status' => 'valid',
        ]);

        $this->get(route('builder.workspace', [$project, $page]))
            ->assertOk()
            ->assertSee('Ship pages with structure')
            ->assertSee('data-node-id=&quot;node_01h00000000000000000000001&quot;', false)
            ->assertSee('/preview-bridge.js', false);
    }

    public function test_workspace_tracks_node_selected_events(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);

        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'document_json' => $this->emptyDocument(),
            'status' => 'draft',
        ]);

        Livewire::test(Workspace::class, ['project' => $project, 'page' => $page])
            ->dispatch('node-selected', nodeId: 'node_01h00000000000000000000001')
            ->assertSet('selected_node_id', 'node_01h00000000000000000000001');
    }

    public function test_generation_controls_enqueue_generate_page_job(): void
    {
        Queue::fake();

        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'document_json' => $this->emptyDocument(),
            'status' => 'draft',
        ]);

        Livewire::test(GenerationControls::class, ['page' => $page])
            ->set('prompt', 'A developer tool landing page')
            ->call('generate')
            ->assertDispatched('generation-started', pageId: $page->id)
            ->assertDispatched('generation-finished', pageId: $page->id, status: 'generating');

        $page->refresh();
        $this->assertSame('A developer tool landing page', $page->prompt);
        $this->assertSame('generating', $page->status);
        Queue::assertPushed(GeneratePageJob::class, fn (GeneratePageJob $job): bool => $job->pageId === $page->id);
    }

    public function test_workspace_updates_generation_status_from_generation_events(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'document_json' => $this->emptyDocument(),
            'status' => 'draft',
        ]);

        Livewire::test(Workspace::class, ['project' => $project, 'page' => $page])
            ->dispatch('generation-started', pageId: $page->id)
            ->assertSet('generation_status', 'running')
            ->dispatch('generation-finished', pageId: $page->id, status: 'error')
            ->assertSet('generation_status', 'error');
    }

    public function test_stream_panel_derives_status_from_page_row(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'document_json' => $this->emptyDocument(),
            'status' => 'error',
        ]);

        Livewire::test(StreamPanel::class, ['page' => $page])
            ->assertSee('error');
    }

    private function emptyDocument(): array
    {
        $now = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return [
            'schema_version' => 1,
            'page_metadata' => [
                'title' => 'Homepage',
                'page_type' => 'landing',
                'goal' => 'Draft a landing page.',
                'audience' => 'General audience',
                'prompt_summary' => 'Empty draft page',
                'status' => 'draft',
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
                'tone' => 'professional',
                'dark_mode' => false,
            ],
            'document_tree' => [],
            'generation_history' => [],
        ];
    }

    private function renderDocument(): array
    {
        $document = $this->emptyDocument();
        $document['page_metadata']['status'] = 'valid';
        $document['document_tree'] = [
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
                        'props' => ['level' => 1, 'text' => 'Ship pages with structure', 'alignment' => 'center', 'emphasis' => 'default'],
                        'locks' => $this->locks(),
                        'metadata' => $this->metadata(),
                    ],
                    [
                        'id' => 'node_01h00000000000000000000002',
                        'type' => 'text',
                        'props' => ['text' => 'A renderer turns page JSON into selectable HTML.', 'size' => 'lg', 'alignment' => 'center', 'emphasis' => 'muted'],
                        'locks' => $this->locks(),
                        'metadata' => $this->metadata(),
                    ],
                    [
                        'id' => 'inst_01h00000000000000000000001',
                        'type' => 'element_instance',
                        'props' => ['library_id' => 'elem_01h00000000000000000000001', 'overrides' => []],
                        'locks' => $this->locks(),
                        'metadata' => $this->metadata('library_instance'),
                    ],
                ],
                'locks' => $this->locks(),
                'metadata' => $this->metadata(),
            ],
        ];

        return $document;
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
