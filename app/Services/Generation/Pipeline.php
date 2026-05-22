<?php

namespace App\Services\Generation;

use App\Models\Page;
use App\Services\Generation\Stages\HtmlMarker;
use App\Services\Generation\Stages\SectionGenerationResult;
use App\Services\Generation\Stages\SectionGenerator;
use App\Services\Generation\Stages\TargetedEdit;
use App\Services\Html\BlockIndexer;
use App\Services\Html\DeterministicBlockMarker;
use App\Services\Html\HtmlDocumentValidator;
use App\Services\Html\HtmlFragmentRepairer;
use App\Services\Html\HtmlSafetySanitizer;
use App\Services\Html\HtmlValidationException;
use App\Services\Rendering\Renderer;
use Throwable;

class Pipeline
{
    public function __construct(
        private readonly GenerationEventRecorder $events,
        private readonly SectionGenerator $sections,
        private readonly HtmlMarker $htmlMarker,
        private readonly TargetedEdit $targetedEdit,
        private readonly DeterministicBlockMarker $deterministicMarker,
        private readonly HtmlDocumentValidator $htmlValidator,
        private readonly HtmlFragmentRepairer $htmlRepairer,
        private readonly HtmlSafetySanitizer $htmlSanitizer,
        private readonly BlockIndexer $blockIndexer,
        private readonly Renderer $renderer,
    ) {}

    public function generate(Page $page, ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $page->forceFill(['status' => 'generating'])->save();

        try {
            $this->events->record($page, 'stage_started', 'section_generator', 'info', 'Designing raw Tailwind HTML.');
            $section = $this->sections->generate($page, $provider, $model, $apiKey);
            $rawHtml = $this->cleanHtml($section->html);
            if ($rawHtml === '') {
                throw new HtmlValidationException(['Section generator returned empty HTML.']);
            }
            $artifact = $this->sectionArtifact($page, $rawHtml);
            $recovery = $section->recovery;
            $this->events->record($page, 'stage_completed', 'section_generator', $recovery === null ? 'success' : 'warning', $this->sectionGeneratorSummary($recovery), payload: [
                'html_bytes' => strlen($rawHtml),
                'recovery' => $recovery,
            ] + $this->payloadWithUsage($section));

            $this->events->record($page, 'stage_started', 'html_marker', 'info', 'Adding editable block markers locally.');
            [$htmlSource, $markerPayload, $markerSummary, $markerLevel] = $this->markHtml($page, $artifact, $rawHtml, $provider, $model, $apiKey);
            $htmlSource = $this->cleanHtml($htmlSource);
            $this->events->record($page, 'stage_completed', 'html_marker', $markerLevel, $markerSummary, payload: $markerPayload);

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

    public function edit(Page $page, string $targetId, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        return $this->editMany($page, [$targetId], $instruction, $provider, $model, $apiKey);
    }

    /**
     * @param  array<int, string>  $targetIds
     */
    public function editMany(Page $page, array $targetIds, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $targetIds = array_values(array_unique(array_filter(
            $targetIds,
            fn (mixed $id): bool => is_string($id) && $id !== '',
        )));
        $eventTargetId = implode(',', $targetIds);

        $this->events->record($page, 'edit_requested', 'targeted_edit', 'info', count($targetIds) > 1 ? 'Editing selected block range.' : 'Editing selected block.', $eventTargetId, [
            'instruction' => $instruction,
            'target_ids' => $targetIds,
        ]);

        try {
            $result = $this->targetedEdit->editMany($page, $targetIds, $instruction, $provider, $model, $apiKey);
            $htmlSource = $this->blockIndexer->replaceBlocks(
                (string) ($page->html_source ?? ''),
                $targetIds,
                $result['html_source'],
            );
            $htmlSource = $this->cleanHtml($htmlSource);

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

            $this->events->record($page, 'edit_applied', 'targeted_edit', 'success', $result['explanation'], $eventTargetId, [
                'blocks' => $result['blocks'] ?? [],
                'target_ids' => $targetIds,
            ] + $this->payloadWithUsage($result));

            return $document;
        } catch (Throwable $exception) {
            $this->events->record($page, 'edit_rejected', 'targeted_edit', 'error', $exception->getMessage(), $eventTargetId);

            throw $exception;
        }
    }

    private function htmlArtifact(array $artifact, string $htmlSource, array $blockIndex): array
    {
        $now = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return [
            'schema_version' => 2,
            'page_metadata' => [
                'title' => $this->scrubText((string) ($artifact['title'] ?? 'Generated page')),
                'page_type' => $this->scrubText((string) ($artifact['page_type'] ?? 'generic')),
                'goal' => $this->scrubText((string) ($artifact['goal'] ?? 'Generated from prompt.')),
                'audience' => $this->scrubText((string) ($artifact['audience'] ?? 'Visitors')),
                'prompt_summary' => $this->scrubText((string) ($artifact['prompt_summary'] ?? '')),
                'status' => 'valid',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'html_source' => $htmlSource,
            'block_index' => $blockIndex,
            'generation_history' => [],
        ];
    }

    private function payloadWithUsage(array|SectionGenerationResult $value, array $payload = []): array
    {
        $llm = $value instanceof SectionGenerationResult ? $value->llm : ($value['_llm'] ?? null);

        if (! is_array($llm)) {
            return $payload;
        }

        return $payload + ['llm' => $llm];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function scrubArray(array $value): array
    {
        foreach ($value as $key => $item) {
            $value[$key] = match (true) {
                is_string($item) => $this->scrubText($item),
                is_array($item) => $this->scrubArray($item),
                default => $item,
            };
        }

        return $value;
    }

    private function scrubText(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_scrub($value, 'UTF-8');
    }

    private function cleanHtml(string $html): string
    {
        return $this->scrubText($this->htmlSanitizer->sanitize($this->htmlRepairer->repair($html)));
    }

    private function sectionGeneratorSummary(mixed $recovery): string
    {
        return match ($recovery) {
            'retry' => 'Raw HTML draft generated after a recovery retry.',
            'deterministic_fallback' => 'Section generator returned empty HTML twice, so a fallback draft was created from the prompt.',
            default => 'Raw HTML draft generated.',
        };
    }

    private function sectionArtifact(Page $page, string $rawHtml): array
    {
        return [
            'title' => $page->name !== '' ? $page->name : 'Generated page',
            'page_type' => 'landing',
            'goal' => $page->prompt !== '' ? str($page->prompt)->limit(180)->toString() : 'Generated from prompt.',
            'audience' => 'Visitors',
            'prompt_summary' => $page->prompt !== '' ? str($page->prompt)->limit(240)->toString() : 'Generated page',
            'raw_html' => $rawHtml,
        ];
    }

    private function markedHtml(array $marked): string
    {
        $html = $marked['html_source'] ?? null;

        if (is_string($html) && trim($html) !== '') {
            return $html;
        }

        return '';
    }

    private function markHtml(Page $page, array $artifact, string $rawHtml, string $provider, ?string $model, ?string $apiKey): array
    {
        $providedMarkedHtml = $this->providedMarkedHtml($artifact);
        if ($providedMarkedHtml !== null) {
            if ($providedMarkedHtml === '') {
                $htmlSource = $this->fallbackMarkedHtml($rawHtml);

                return [$htmlSource, [
                    'html_bytes' => strlen($htmlSource),
                    'fallback' => true,
                    'strategy' => 'single_block_fallback',
                ], 'Marker returned empty HTML, so the raw draft was wrapped as one editable block.', 'warning'];
            }

            return [$providedMarkedHtml, [
                'html_bytes' => strlen($providedMarkedHtml),
                'strategy' => 'provided',
            ], 'Editable block markers were already present.', 'success'];
        }

        $htmlSource = $this->deterministicMarker->mark($rawHtml);

        if ($htmlSource !== '') {
            try {
                $this->htmlValidator->assertValid($htmlSource);

                return [$htmlSource, [
                    'html_bytes' => strlen($htmlSource),
                    'strategy' => 'deterministic',
                    'blocks' => count($this->blockIndexer->index($htmlSource)),
                ], 'Editable block markers added locally.', 'success'];
            } catch (HtmlValidationException $exception) {
                if ($this->hasUnsafeHtmlError($exception->errors)) {
                    throw $exception;
                }

                $this->events->record($page, 'stage_progress', 'html_marker', 'warning', 'Local marker could not validate, trying LLM marker.', payload: [
                    'strategy' => 'deterministic',
                    'errors' => $exception->errors,
                ]);
            }
        }

        $marked = $this->htmlMarker->mark($page, $artifact, $provider, $model, $apiKey);
        $htmlSource = $this->markedHtml($marked);

        if ($htmlSource === '') {
            $htmlSource = $this->fallbackMarkedHtml($rawHtml);

            return [$htmlSource, [
                'html_bytes' => strlen($htmlSource),
                'fallback' => true,
                'strategy' => 'single_block_fallback',
            ] + $this->payloadWithUsage($marked), 'Marker returned empty HTML, so the raw draft was wrapped as one editable block.', 'warning'];
        }

        return [$htmlSource, [
            'html_bytes' => strlen($htmlSource),
            'strategy' => 'llm',
        ] + $this->payloadWithUsage($marked), 'Editable block markers added by LLM fallback.', 'success'];
    }

    private function providedMarkedHtml(array $artifact): ?string
    {
        foreach (['marked_html', 'html_source', 'raw_html'] as $key) {
            if (! array_key_exists($key, $artifact)) {
                continue;
            }

            $html = $artifact[$key];
            if (! is_string($html)) {
                continue;
            }

            if (trim($html) === '' || preg_match('/<!--\s*tw:block\b/i', $html)) {
                return $html;
            }
        }

        return null;
    }

    private function hasUnsafeHtmlError(array $errors): bool
    {
        foreach ($errors as $error) {
            if (str_contains($error, 'script tags')
                || str_contains($error, 'inline event handler')
                || str_contains($error, 'javascript: URLs')) {
                return true;
            }
        }

        return false;
    }

    private function fallbackMarkedHtml(string $rawHtml): string
    {
        if (preg_match('/<!--\s*tw:block\b/i', $rawHtml)) {
            return $rawHtml;
        }

        return '<!-- tw:block id="block_page" type="custom" label="Page" -->'."\n"
            .'<div data-node-id="block_page" data-node-type="custom" data-tw-block="block_page">'."\n"
            .$rawHtml."\n"
            .'</div>'."\n"
            .'<!-- /tw:block -->';
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
