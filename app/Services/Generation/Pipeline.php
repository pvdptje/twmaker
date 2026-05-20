<?php

namespace App\Services\Generation;

use App\Models\Page;
use App\Services\Generation\Stages\HtmlMarker;
use App\Services\Generation\Stages\Planner;
use App\Services\Generation\Stages\SectionGenerator;
use App\Services\Generation\Stages\TargetedEdit;
use App\Services\Html\BlockIndexer;
use App\Services\Html\HtmlDocumentValidator;
use App\Services\Rendering\Renderer;
use Throwable;

class Pipeline
{
    public function __construct(
        private readonly GenerationEventRecorder $events,
        private readonly Planner $planner,
        private readonly SectionGenerator $sections,
        private readonly HtmlMarker $htmlMarker,
        private readonly TargetedEdit $targetedEdit,
        private readonly HtmlDocumentValidator $htmlValidator,
        private readonly BlockIndexer $blockIndexer,
        private readonly Renderer $renderer,
    ) {}

    public function generate(Page $page): array
    {
        $page->forceFill(['status' => 'generating'])->save();
        $this->events->record($page, 'stage_started', 'planner', 'info', 'Planning page structure.');

        try {
            $plan = $this->planner->plan($page);
            $this->events->record($page, 'stage_completed', 'planner', 'success', 'Planner produced a page outline.', payload: $plan);

            $this->events->record($page, 'stage_started', 'section_generator', 'info', 'Generating raw Tailwind HTML.');
            $artifact = $this->sections->generate($page, $plan);
            $this->events->record($page, 'stage_completed', 'section_generator', 'success', 'Raw HTML draft generated.');

            $this->events->record($page, 'stage_started', 'html_marker', 'info', 'Adding editable block markers.');
            $marked = $this->htmlMarker->mark($page, $plan, $artifact);
            $this->events->record($page, 'stage_completed', 'html_marker', 'success', 'Editable block markers added.');

            $htmlSource = $this->htmlSource($marked, $artifact);
            $this->events->record($page, 'stage_started', 'validation', 'info', 'Validating marked HTML.');
            $this->htmlValidator->assertValid($htmlSource);
            $blockIndex = $this->blockIndexer->index($htmlSource);
            $this->events->record($page, 'stage_completed', 'validation', 'success', 'Marked HTML is valid.', payload: [
                'blocks' => count($blockIndex),
            ]);
            $document = $this->htmlArtifact($artifact, $htmlSource, $blockIndex);

            $page->forceFill([
                'document_json' => $document,
                'html_source' => $htmlSource,
                'block_index' => $blockIndex,
                'rendered_html_cache' => $this->renderer->renderPreviewHtml($htmlSource, $artifact['title'] ?? $page->name),
                'status' => 'valid',
                'last_generation_summary' => $document['page_metadata']['prompt_summary'] ?? null,
            ])->save();

            $this->events->record($page, 'generation_completed', 'validation', 'success', 'Generated document is valid.');

            return $document;
        } catch (Throwable $exception) {
            $page->forceFill(['status' => 'error'])->save();
            $this->events->record($page, 'generation_failed', 'pipeline', 'error', $exception->getMessage());

            throw $exception;
        }
    }

    public function edit(Page $page, string $targetId, string $instruction): array
    {
        $this->events->record($page, 'edit_requested', 'targeted_edit', 'info', 'Editing selected block.', $targetId, [
            'instruction' => $instruction,
        ]);

        try {
            $result = $this->targetedEdit->edit($page, $targetId, $instruction);
            $htmlSource = $this->blockIndexer->replaceBlock(
                (string) ($page->html_source ?? ''),
                $targetId,
                $result['html_source'],
            );

            $this->htmlValidator->assertValid($htmlSource);
            $blockIndex = $this->blockIndexer->index($htmlSource);
            $document = $this->htmlArtifact(
                $this->editArtifact($page, $instruction),
                $htmlSource,
                $blockIndex,
            );

            $page->forceFill([
                'document_json' => $document,
                'html_source' => $htmlSource,
                'block_index' => $blockIndex,
                'rendered_html_cache' => $this->renderer->renderPreviewHtml($htmlSource, $page->name),
                'status' => 'valid',
                'last_generation_summary' => $document['page_metadata']['prompt_summary'] ?? null,
            ])->save();

            $this->events->record($page, 'edit_applied', 'targeted_edit', 'success', $result['explanation'], $targetId, [
                'blocks' => $result['blocks'] ?? [],
            ]);

            return $document;
        } catch (Throwable $exception) {
            $this->events->record($page, 'edit_rejected', 'targeted_edit', 'error', $exception->getMessage(), $targetId);

            throw $exception;
        }
    }

    private function htmlArtifact(array $artifact, string $htmlSource, array $blockIndex): array
    {
        $now = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return [
            'schema_version' => 2,
            'page_metadata' => [
                'title' => (string) ($artifact['title'] ?? 'Generated page'),
                'page_type' => (string) ($artifact['page_type'] ?? 'generic'),
                'goal' => (string) ($artifact['goal'] ?? 'Generated from prompt.'),
                'audience' => (string) ($artifact['audience'] ?? 'Visitors'),
                'prompt_summary' => (string) ($artifact['prompt_summary'] ?? ''),
                'status' => 'valid',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'html_source' => $htmlSource,
            'block_index' => $blockIndex,
            'generation_history' => [],
        ];
    }

    private function htmlSource(array $marked, array $artifact): string
    {
        foreach ([$marked['html_source'] ?? null, $artifact['html_source'] ?? null, $artifact['raw_html'] ?? null] as $html) {
            if (is_string($html) && trim($html) !== '') {
                return $html;
            }
        }

        return '';
    }

    private function editArtifact(Page $page, string $instruction): array
    {
        $metadata = $page->document_json['page_metadata'] ?? [];

        return [
            'title' => $metadata['title'] ?? $page->name,
            'page_type' => $metadata['page_type'] ?? 'generic',
            'goal' => $metadata['goal'] ?? 'Edited from builder instruction.',
            'audience' => $metadata['audience'] ?? 'Visitors',
            'prompt_summary' => 'Targeted edit: '.str($instruction)->limit(120),
        ];
    }
}
