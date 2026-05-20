<?php

namespace Tests\Feature;

use App\Livewire\Projects\ProjectDashboard\ProjectDashboard;
use App\Livewire\Projects\ProjectList\ProjectList;
use App\Models\Page;
use App\Models\Project;
use App\Services\Ids\IdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('/preview.css', false);
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
}
