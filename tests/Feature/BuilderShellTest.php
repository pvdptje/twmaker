<?php

namespace Tests\Feature;

use App\Jobs\GeneratePageJob;
use App\Jobs\TargetedEditJob;
use App\Livewire\Builder\Inspector\EditForm\EditForm;
use App\Livewire\Builder\Inspector\VersionList\VersionList;
use App\Livewire\Builder\RightInspector\RightInspector;
use App\Livewire\Builder\SidePanels\GenerationControls\GenerationControls;
use App\Livewire\Builder\StreamPanel\StreamPanel;
use App\Livewire\Builder\Workspace\Workspace;
use App\Livewire\Projects\ProjectDashboard\ProjectDashboard;
use App\Livewire\Projects\ProjectList\ProjectList;
use App\Livewire\Setup\LlmSetup;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Project;
use App\Services\Html\BlockIndexer;
use App\Services\Ids\IdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    public function test_llm_setup_page_renders_provider_keys_and_defaults(): void
    {
        $this->get(route('setup.llm'))
            ->assertOk()
            ->assertSee('LLM setup')
            ->assertSee('Provider keys')
            ->assertSee('stored only in this browser')
            ->assertSee('Server env keys are optional fallbacks')
            ->assertSee('Primary generation')
            ->assertSee('Editing')
            ->assertSee('Anthropic')
            ->assertSee('DeepSeek')
            ->assertSee('OpenAI')
            ->assertSee('Gemini');
    }

    public function test_llm_setup_saves_browser_backed_defaults(): void
    {
        $this->cacheProviderModels('anthropic', 'test-setup-key', [
            ['id' => 'claude-haiku-4-5-20251001', 'label' => 'Claude Haiku 4.5'],
            ['id' => 'claude-sonnet-4-20250514', 'label' => 'Claude Sonnet 4'],
        ]);

        Livewire::test(LlmSetup::class)
            ->set('apiKeys.anthropic', 'test-setup-key')
            ->set('primaryProvider', 'anthropic')
            ->set('primaryModel', 'claude-haiku-4-5-20251001')
            ->set('editingProvider', 'anthropic')
            ->set('editingModel', 'claude-sonnet-4-20250514')
            ->call('save')
            ->assertSet('saveStatus', 'Setup saved on this browser.');
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
        $this->assertNull($page->html_source);
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
            'status' => 'draft',
        ]);

        $this->get(route('builder.workspace', [$project, $page]))
            ->assertOk()
            ->assertSee('Canvas')
            ->assertSee('Inspector')
            ->assertSee('Activity')
            ->assertSee('No generation events yet.')
            ->assertSee('maxRows: 80', false)
            ->assertSee('/preview.css', false);
    }

    public function test_workspace_renders_marked_html_in_preview_srcdoc(): void
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
            'html_source' => $this->markedHtmlSource(),
            'status' => 'valid',
        ]);

        $this->get(route('builder.workspace', [$project, $page]))
            ->assertOk()
            ->assertSee('Ship pages with marked blocks')
            ->assertSee('Download HTML')
            ->assertSee('tw:block id=&quot;block_hero&quot;', false)
            ->assertSee('alpinejs@3.x.x', false)
            ->assertSee('/preview-bridge.js', false);
    }

    public function test_page_html_download_returns_current_html_as_attachment(): void
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
            'html_source' => $this->markedHtmlSource(),
            'status' => 'valid',
        ]);

        $this->get(route('builder.pages.download-html', [$project, $page]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertDownload('homepage.html')
            ->assertSee('<!doctype html>', false)
            ->assertSee('Ship pages with marked blocks')
            ->assertSee('https://cdn.tailwindcss.com', false)
            ->assertSee('alpinejs@3.x.x', false)
            ->assertDontSee('/preview-bridge.js', false);
    }

    public function test_page_html_download_requires_page_to_belong_to_project(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $otherProject = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Other',
        ]);

        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'html_source' => $this->markedHtmlSource(),
            'status' => 'valid',
        ]);

        $this->get(route('builder.pages.download-html', [$otherProject, $page]))
            ->assertNotFound();
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
            'status' => 'draft',
        ]);

        Livewire::test(Workspace::class, ['project' => $project, 'page' => $page])
            ->dispatch('node-selected', nodeId: 'node_01h00000000000000000000001')
            ->assertSet('selected_node_id', 'node_01h00000000000000000000001')
            ->assertDispatched('preview-selection-changed', nodeId: 'node_01h00000000000000000000001', scrollIntoView: true)
            ->dispatch('node-selected', nodeId: 'node_01h00000000000000000000002', scrollIntoView: false)
            ->assertSet('selected_node_id', 'node_01h00000000000000000000002')
            ->assertNotDispatched('preview-selection-changed', nodeId: 'node_01h00000000000000000000002', scrollIntoView: false);
    }

    public function test_workspace_tracks_multi_block_selection_events(): void
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
            'status' => 'draft',
        ]);

        Livewire::test(Workspace::class, ['project' => $project, 'page' => $page])
            ->dispatch('block-selection-toggled', blockId: 'block_hero')
            ->assertSet('selected_block_ids', ['block_hero'])
            ->dispatch('block-selection-toggled', blockId: 'block_features')
            ->assertSet('selected_block_ids', ['block_hero', 'block_features'])
            ->dispatch('block-selection-toggled', blockId: 'block_hero')
            ->assertSet('selected_block_ids', ['block_features']);
    }

    public function test_generation_controls_enqueue_generate_page_job(): void
    {
        Queue::fake();
        $this->cacheProviderModels('anthropic', 'test-generation-key', [
            ['id' => 'claude-haiku-4-5-20251001', 'label' => 'Claude Haiku 4.5'],
        ]);

        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'status' => 'draft',
        ]);

        Livewire::test(GenerationControls::class, ['page' => $page])
            ->set('prompt', 'A developer tool landing page')
            ->set('provider', 'anthropic')
            ->set('model', 'claude-haiku-4-5-20251001')
            ->set('apiKey', 'test-generation-key')
            ->call('generate')
            ->assertDispatched('generation-started', pageId: $page->id)
            ->assertDispatched('generation-finished', pageId: $page->id, status: 'generating');

        $page->refresh();
        $this->assertSame('A developer tool landing page', $page->prompt);
        $this->assertSame('generating', $page->status);
        Queue::assertPushed(GeneratePageJob::class, fn (GeneratePageJob $job): bool => $job->pageId === $page->id
            && $job->provider === 'anthropic'
            && $job->model === 'claude-haiku-4-5-20251001'
            && $job->apiKey === 'test-generation-key');
    }

    public function test_generation_controls_refresh_provider_models(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.anthropic.com/v1/models*' => Http::response([
                'data' => [
                    [
                        'id' => 'claude-fresh-20260521',
                        'display_name' => 'Claude Fresh',
                    ],
                ],
                'has_more' => false,
            ]),
        ]);

        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'status' => 'draft',
        ]);

        Livewire::test(GenerationControls::class, ['page' => $page])
            ->set('apiKey', 'test-model-key')
            ->call('refreshModels')
            ->assertSet('model', 'claude-fresh-20260521')
            ->assertSee('Claude Fresh')
            ->assertSee('1 models refreshed.');

        Http::assertSentCount(1);
    }

    public function test_generation_controls_enqueue_selected_deepseek_model(): void
    {
        Queue::fake();
        $this->cacheProviderModels('deepseek', 'test-deepseek-key', [
            ['id' => 'deepseek-reasoner', 'label' => 'DeepSeek Reasoner'],
        ]);

        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'status' => 'draft',
        ]);

        Livewire::test(GenerationControls::class, ['page' => $page])
            ->set('prompt', 'A developer tool landing page')
            ->call('generateWithSelection', 'deepseek', 'deepseek-reasoner', 'test-deepseek-key')
            ->assertDispatched('generation-started', pageId: $page->id);

        Queue::assertPushed(GeneratePageJob::class, fn (GeneratePageJob $job): bool => $job->provider === 'deepseek'
            && $job->model === 'deepseek-reasoner'
            && $job->apiKey === 'test-deepseek-key');
    }

    public function test_edit_form_enqueues_targeted_edit_job_for_selected_node(): void
    {
        Queue::fake();
        $this->cacheProviderModels('anthropic', 'test-edit-key', [
            ['id' => 'claude-haiku-4-5-20251001', 'label' => 'Claude Haiku 4.5'],
        ]);

        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'status' => 'valid',
        ]);

        Livewire::test(EditForm::class, ['page' => $page, 'selectedNodeId' => 'block_hero'])
            ->set('instruction', 'Replace this with a stronger intro')
            ->set('provider', 'anthropic')
            ->set('model', 'claude-haiku-4-5-20251001')
            ->set('apiKey', 'test-edit-key')
            ->call('applyEdit')
            ->assertSet('instruction', '')
            ->assertDispatched('generation-started', pageId: $page->id, stage: 'targeted_edit');

        $this->assertSame('generating', $page->refresh()->status);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'edit_requested',
            'stage' => 'targeted_edit',
            'target_id' => 'block_hero',
        ]);

        Queue::assertPushed(TargetedEditJob::class, fn (TargetedEditJob $job): bool => $job->pageId === $page->id
            && $job->targetId === 'block_hero'
            && $job->instruction === 'Replace this with a stronger intro'
            && $job->provider === 'anthropic'
            && $job->model === 'claude-haiku-4-5-20251001'
            && $job->apiKey === 'test-edit-key');
    }

    public function test_edit_form_enqueues_targeted_edit_job_for_selected_blocks(): void
    {
        Queue::fake();
        $this->cacheProviderModels('anthropic', 'test-edit-key', [
            ['id' => 'claude-haiku-4-5-20251001', 'label' => 'Claude Haiku 4.5'],
        ]);

        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'status' => 'valid',
        ]);

        Livewire::test(EditForm::class, ['page' => $page, 'selectedBlockIds' => ['block_hero', 'block_features']])
            ->set('instruction', 'Replace these with one product story section')
            ->set('provider', 'anthropic')
            ->set('model', 'claude-haiku-4-5-20251001')
            ->set('apiKey', 'test-edit-key')
            ->call('applyEdit')
            ->assertSet('instruction', '')
            ->assertDispatched('generation-started', pageId: $page->id, stage: 'targeted_edit');

        Queue::assertPushed(TargetedEditJob::class, fn (TargetedEditJob $job): bool => $job->targetId === ['block_hero', 'block_features']
            && $job->instruction === 'Replace these with one product story section');
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
            'status' => 'draft',
        ]);

        Livewire::test(Workspace::class, ['project' => $project, 'page' => $page])
            ->dispatch('generation-started', pageId: $page->id)
            ->assertSet('generation_status', 'running')
            ->dispatch('generation-finished', pageId: $page->id, status: 'error')
            ->assertSet('generation_status', 'error');
    }

    public function test_workspace_refreshes_generated_html_state_when_broadcast_finishes(): void
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
            'status' => 'generating',
        ]);

        $component = Livewire::test(Workspace::class, ['project' => $project, 'page' => $page])
            ->assertSet('generation_status', 'idle');

        $page->forceFill([
            'status' => 'valid',
            'html_source' => '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section>Hello</section><!-- /tw:block -->',
        ])->save();

        $component
            ->dispatch('generation-finished', pageId: $page->id, status: 'valid')
            ->assertSet('generation_status', 'valid')
            ->assertSet('block_index', [
                [
                    'id' => 'block_hero',
                    'type' => 'hero',
                    'label' => 'Hero',
                    'summary' => 'Hello',
                ],
            ]);
    }

    public function test_workspace_section_browser_derives_current_html_blocks(): void
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
            'html_source' => <<<'HTML'
<!-- tw:block id="block_hero" type="hero" label="Hero" -->
<section>Hero</section>
<!-- /tw:block -->
<!-- tw:block id="block_features" type="features" label="Features" -->
<section>Features</section>
<!-- /tw:block -->
HTML,
            'status' => 'valid',
        ]);

        Livewire::test(Workspace::class, ['project' => $project, 'page' => $page])
            ->assertSet('block_index', [
                [
                    'id' => 'block_hero',
                    'type' => 'hero',
                    'label' => 'Hero',
                    'summary' => 'Hero',
                ],
                [
                    'id' => 'block_features',
                    'type' => 'features',
                    'label' => 'Features',
                    'summary' => 'Features',
                ],
            ]);
    }

    public function test_workspace_resyncs_selected_preview_after_generated_html_changes(): void
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
            'status' => 'valid',
        ]);

        $component = Livewire::test(Workspace::class, ['project' => $project, 'page' => $page])
            ->dispatch('node-selected', nodeId: 'block_hero')
            ->assertSet('selected_node_id', 'block_hero');

        $page->forceFill([
            'html_source' => '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section>Updated</section><!-- /tw:block -->',
        ])->save();

        $component
            ->dispatch('generation-finished', pageId: $page->id, status: 'valid')
            ->assertDispatched('preview-html-updated')
            ->assertDispatched('preview-selection-changed', nodeId: 'block_hero', scrollIntoView: true);
    }

    public function test_workspace_can_finish_incremental_targeted_edit_without_remounting_preview(): void
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
            'html_source' => '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section>Hero</section><!-- /tw:block -->',
            'status' => 'generating',
        ]);

        $component = Livewire::test(Workspace::class, ['project' => $project, 'page' => $page]);
        $previewMountKey = $component->get('preview_mount_key');

        $page->forceFill([
            'html_source' => '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section>Edited hero</section><!-- /tw:block -->',
            'status' => 'valid',
        ])->save();

        $component
            ->dispatch('generation-finished', pageId: $page->id, status: 'valid', incremental: true)
            ->assertSet('generation_status', 'valid')
            ->assertSet('preview_mount_key', $previewMountKey)
            ->assertNotDispatched('preview-html-updated');
    }

    public function test_workspace_saves_quick_dom_element_edits(): void
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
            'html_source' => '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section class="px-6 py-24"><h1>Ship pages</h1><p class="mt-4">Fast</p></section><!-- /tw:block -->',
            'status' => 'valid',
        ]);

        $component = Livewire::test(Workspace::class, ['project' => $project, 'page' => $page]);
        $previewMountKey = $component->get('preview_mount_key');

        $component
            ->dispatch('quick-edit-save', editId: 'block_hero:1', html: '<p class="mt-8 text-lg text-neutral-600">Better website copy here.</p>')
            ->assertDispatched('quick-edit-saved')
            ->assertSet('preview_mount_key', $previewMountKey);

        $page->refresh();

        $this->assertStringContainsString('<!-- tw:block id="block_hero" type="hero" label="Hero" -->', $page->html_source);
        $this->assertStringContainsString('<h1>Ship pages</h1>', $page->html_source);
        $this->assertStringContainsString('<p class="mt-8 text-lg text-neutral-600">Better website copy here.</p>', $page->html_source);
        $this->assertStringContainsString('<!-- /tw:block -->', $page->html_source);
        $blockIndex = app(BlockIndexer::class)->index($page->html_source);
        $this->assertStringContainsString('Better website copy here.', $blockIndex[0]['summary']);
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
            'status' => 'error',
        ]);

        Livewire::test(StreamPanel::class, ['page' => $page])
            ->assertSee('error');
    }

    public function test_stream_panel_shows_loader_while_running(): void
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
            'status' => 'generating',
        ]);

        Livewire::test(StreamPanel::class, ['page' => $page])
            ->assertSee('running')
            ->assertSee('Activity')
            ->assertSee('generation-event-received', false)
            ->assertDontSee('data-stream-url=', false)
            ->assertDontSee('fetchSnapshot()', false)
            ->assertDontSee('wire:poll', false)
            ->assertSee('bg-gradient-to-r', false);
    }

    public function test_workspace_contains_broadcast_only_realtime_bridge(): void
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
            'status' => 'valid',
        ]);

        Livewire::test(Workspace::class, ['project' => $project, 'page' => $page])
            ->assertSee('data-builder-workspace-page-id="'.$page->id.'"', false)
            ->assertSee('generation-event-received', false)
            ->assertDontSee('setInterval', false)
            ->assertDontSee('refreshTimer', false);
    }

    public function test_stream_panel_has_no_stream_modal_controls(): void
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
            'status' => 'generating',
        ]);

        Livewire::test(StreamPanel::class, ['page' => $page])
            ->assertSee('Activity')
            ->assertDontSee('Open stream')
            ->assertDontSee('Template stream')
            ->assertDontSee('x-show="open"', false);
    }

    public function test_stream_panel_tracks_targeted_edit_activity_without_modal_state(): void
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
            'status' => 'generating',
        ]);
        $page->generationEvents()->create([
            'id' => app(IdGenerator::class)->generationEvent(),
            'kind' => 'edit_requested',
            'stage' => 'targeted_edit',
            'target_id' => 'block_hero',
            'level' => 'info',
            'summary' => 'Editing selected block.',
            'payload' => [
                'target_ids' => ['block_hero'],
            ],
            'occurred_at' => now('UTC'),
        ]);

        Livewire::test(StreamPanel::class, ['page' => $page])
            ->assertSet('activeStage', 'targeted_edit')
            ->assertSee('targeted_edit')
            ->assertSee('Editing selected block.')
            ->assertDontSee('open:', false)
            ->assertDontSee('shouldAutoOpen', false);
    }

    public function test_edit_form_button_shows_running_state_until_generation_finishes(): void
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
            'status' => 'valid',
        ]);

        Livewire::test(EditForm::class, ['page' => $page, 'selectedNodeId' => 'block_hero'])
            ->assertSee('editRunning', false)
            ->assertSee('Applying edit', false)
            ->assertSee('x-on:generation-finished.window="finishEdit($event)"', false)
            ->assertSee('animate-spin', false);
    }

    public function test_right_inspector_shows_token_totals_per_model(): void
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
            'status' => 'valid',
        ]);

        $page->generationEvents()->create([
            'id' => app(IdGenerator::class)->generationEvent(),
            'kind' => 'stage_completed',
            'stage' => 'section_generator',
            'level' => 'success',
            'summary' => 'Raw HTML draft generated.',
            'payload' => [
                'llm' => [
                    'provider' => 'anthropic',
                    'model' => 'claude-sonnet-4-20250514',
                    'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                ],
            ],
            'occurred_at' => now('UTC'),
        ]);
        $page->generationEvents()->create([
            'id' => app(IdGenerator::class)->generationEvent(),
            'kind' => 'edit_applied',
            'stage' => 'targeted_edit',
            'level' => 'success',
            'summary' => 'Edited selected block.',
            'payload' => [
                'llm' => [
                    'provider' => 'anthropic',
                    'model' => 'claude-sonnet-4-20250514',
                    'usage' => ['input_tokens' => 25, 'output_tokens' => 25],
                ],
            ],
            'occurred_at' => now('UTC'),
        ]);

        Livewire::test(RightInspector::class, ['page' => $page])
            ->assertSee('Tokens')
            ->assertSee('claude-sonnet-4-20250514')
            ->assertSee('200 total');
    }

    public function test_version_list_restores_a_previous_version(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => 'A landing page',
            'html_source' => '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section><h1>Current</h1></section><!-- /tw:block -->',
            'status' => 'valid',
        ]);
        $previousHtml = '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section><h1>Previous</h1></section><!-- /tw:block -->';
        $previousVersion = PageVersion::query()->create([
            'id' => app(IdGenerator::class)->pageVersion(),
            'page_id' => $page->id,
            'html_source' => $previousHtml,
            'created_by_kind' => 'generation',
            'summary' => 'Initial generation',
            'created_at' => now('UTC')->subMinute(),
        ]);
        PageVersion::query()->create([
            'id' => app(IdGenerator::class)->pageVersion(),
            'page_id' => $page->id,
            'html_source' => $page->html_source,
            'created_by_kind' => 'edit',
            'summary' => 'Latest edit',
            'created_at' => now('UTC'),
        ]);

        Livewire::test(VersionList::class, ['page' => $page])
            ->assertSee('Initial generation')
            ->assertSee('Latest edit')
            ->call('restore', $previousVersion->id)
            ->assertDispatched('generation-finished', pageId: $page->id, status: 'valid', incremental: false);

        $page->refresh();
        $this->assertStringContainsString('Previous', $page->html_source);
    }

    public function test_version_list_keeps_restored_version_active_after_self_dispatched_event(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => 'A landing page',
            'html_source' => '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section><h1>Latest</h1></section><!-- /tw:block -->',
            'status' => 'valid',
        ]);
        $older = PageVersion::query()->create([
            'id' => app(IdGenerator::class)->pageVersion(),
            'page_id' => $page->id,
            'html_source' => '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section><h1>Older</h1></section><!-- /tw:block -->',
            'created_by_kind' => 'generation',
            'summary' => 'Initial generation',
            'created_at' => now('UTC')->subMinutes(2),
        ]);
        $newest = PageVersion::query()->create([
            'id' => app(IdGenerator::class)->pageVersion(),
            'page_id' => $page->id,
            'html_source' => $page->html_source,
            'created_by_kind' => 'edit',
            'summary' => 'Latest edit',
            'created_at' => now('UTC'),
        ]);

        Livewire::test(VersionList::class, ['page' => $page])
            ->assertSet('activeVersionId', $newest->id)
            ->call('restore', $older->id)
            ->assertSet('activeVersionId', $older->id)
            ->call('refreshOnGenerationFinish', pageId: $page->id, status: 'valid', incremental: false)
            ->assertSet('activeVersionId', $older->id)
            ->assertSet('pendingRestoreVersionId', null);
    }

    public function test_version_list_renders_empty_state_when_no_versions_exist(): void
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
            'status' => 'draft',
        ]);

        Livewire::test(VersionList::class, ['page' => $page])
            ->assertSee('No snapshots yet');
    }

    private function markedHtmlSource(): string
    {
        return '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section class="px-6 py-24"><h1>Ship pages with marked blocks</h1></section><!-- /tw:block -->';
    }

    private function cacheProviderModels(string $provider, string $apiKey, array $models): void
    {
        Cache::put("llm:models:{$provider}:".hash('sha256', $apiKey), $models, now()->addMinute());
    }
}
