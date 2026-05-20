<?php

namespace App\Livewire\Projects\ProjectDashboard;

use App\Models\Page;
use App\Models\Project;
use App\Services\Ids\IdGenerator;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProjectDashboard extends Component
{
    public Project $project;

    public string $name = '';

    public string $prompt = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function createPage(IdGenerator $ids): mixed
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'prompt' => ['nullable', 'string'],
        ]);

        $page = Page::query()->create([
            'id' => $ids->page(),
            'project_id' => $this->project->id,
            'name' => $validated['name'],
            'prompt' => $validated['prompt'] ?: '',
            'document_json' => $this->emptyDocument($validated['name'], $validated['prompt'] ?: ''),
            'rendered_html_cache' => null,
            'status' => 'draft',
            'last_generation_summary' => null,
        ]);

        return $this->redirectRoute('builder.workspace', [$this->project, $page], navigate: true);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/project-dashboard.blade.php', [
            'pages' => $this->project->pages()->latest()->get(),
        ])->layout('components.layouts.app', ['title' => $this->project->name]);
    }

    private function emptyDocument(string $title, string $prompt): array
    {
        $now = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return [
            'schema_version' => 1,
            'page_metadata' => [
                'title' => $title,
                'page_type' => 'landing',
                'goal' => 'Draft a landing page.',
                'audience' => 'General audience',
                'prompt_summary' => $prompt !== '' ? $prompt : 'Empty draft page',
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
