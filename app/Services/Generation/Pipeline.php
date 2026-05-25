<?php

namespace App\Services\Generation;

use App\Models\Page;
use App\Models\PageVersion;
use App\Services\Generation\Stages\DocumentEnhancer;
use App\Services\Generation\Stages\HtmlMarker;
use App\Services\Generation\Stages\SectionGenerationResult;
use App\Services\Generation\Stages\SectionGenerator;
use App\Services\Generation\Stages\SectionInserter;
use App\Services\Generation\Stages\TargetedEdit;
use App\Services\Html\BlockIndexer;
use App\Services\Html\DeterministicBlockMarker;
use App\Services\Html\HtmlDocumentValidator;
use App\Services\Html\HtmlFragmentRepairer;
use App\Services\Html\HtmlSafetySanitizer;
use App\Services\Html\HtmlValidationException;
use App\Services\Ids\IdGenerator;
use App\Services\Rendering\Renderer;
use Throwable;

class Pipeline
{
    public function __construct(
        private readonly GenerationEventRecorder $events,
        private readonly SectionGenerator $sections,
        private readonly HtmlMarker $htmlMarker,
        private readonly TargetedEdit $targetedEdit,
        private readonly SectionInserter $sectionInserter,
        private readonly DocumentEnhancer $documentEnhancer,
        private readonly DeterministicBlockMarker $deterministicMarker,
        private readonly HtmlDocumentValidator $htmlValidator,
        private readonly HtmlFragmentRepairer $htmlRepairer,
        private readonly HtmlSafetySanitizer $htmlSanitizer,
        private readonly BlockIndexer $blockIndexer,
        private readonly Renderer $renderer,
        private readonly IdGenerator $ids,
    ) {}

    private function snapshotVersion(Page $page, string $kind, string $summary): void
    {
        $html = (string) ($page->html_source ?? '');
        if (trim($html) === '') {
            return;
        }

        PageVersion::query()->create([
            'id' => $this->ids->pageVersion(),
            'page_id' => $page->id,
            'html_source' => $html,
            'created_by_kind' => $kind,
            'summary' => $this->scrubText(str($summary)->limit(280)->toString()),
            'created_at' => now('UTC'),
        ]);
    }

    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    public function generate(Page $page, ?string $provider = null, ?string $model = null, ?string $apiKey = null, array $images = []): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $page->forceFill(['status' => 'generating'])->save();

        try {
            $this->events->record($page, 'stage_started', 'section_generator', 'info', 'Designing raw Tailwind HTML.', payload: [
                'reference_images' => count($images),
            ]);
            $section = $this->sections->generate($page, $provider, $model, $apiKey, $images);
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
            $blockIndex = $this->scrubArray($this->blockIndexer->index($htmlSource));
            $this->events->record($page, 'stage_completed', 'validation', 'success', 'Marked HTML is valid.', payload: [
                'blocks' => count($blockIndex),
            ]);
            $document = $this->scrubArray($this->htmlArtifact($artifact, $htmlSource, $blockIndex));

            $page->forceFill([
                'html_source' => $htmlSource,
                'rendered_html_cache' => $this->renderer->renderPreviewHtml($htmlSource, $artifact['title'] ?? $page->name),
                'status' => 'valid',
                'last_generation_summary' => $document['page_metadata']['prompt_summary'] ?? null,
            ])->save();

            $this->snapshotVersion($page, 'generation', $page->prompt !== '' ? $page->prompt : 'Initial generation');

            $this->events->record($page, 'generation_completed', 'validation', 'success', 'Generated document is valid.');

            return $document;
        } catch (Throwable $exception) {
            $page->forceFill(['status' => 'error'])->save();
            $this->events->record($page, 'generation_failed', 'pipeline', 'error', $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    public function edit(Page $page, string $targetId, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null, array $images = []): array
    {
        return $this->editMany($page, [$targetId], $instruction, $provider, $model, $apiKey, $images);
    }

    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    public function insertSection(Page $page, ?string $anchorBlockId, string $position, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null, array $images = []): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $position = $position === 'before' ? 'before' : 'after';
        $eventTargetId = $anchorBlockId !== null && $anchorBlockId !== '' ? $anchorBlockId : null;

        if (! $this->hasFreshInsertRequestEvent($page, $eventTargetId, $position)) {
            $this->events->record($page, 'insert_requested', 'section_inserter', 'info', $this->insertRequestedSummary($eventTargetId, $position), $eventTargetId, [
                'instruction' => $instruction,
                'anchor_id' => $eventTargetId,
                'position' => $position,
                'reference_images' => count($images),
            ]);
        }

        try {
            $result = $this->sectionInserter->insert($page, $eventTargetId, $position, $instruction, $provider, $model, $apiKey, $images);
            $htmlSource = $this->blockIndexer->insertBlocks(
                (string) ($page->html_source ?? ''),
                (string) ($eventTargetId ?? ''),
                $position,
                $result['html_source'],
            );
            $htmlSource = $this->cleanHtml($htmlSource);

            $this->htmlValidator->assertValid($htmlSource);
            $blockIndex = $this->scrubArray($this->blockIndexer->index($htmlSource));
            $document = $this->scrubArray($this->htmlArtifact(
                $this->insertArtifact($page, $instruction),
                $htmlSource,
                $blockIndex,
            ));

            $page->forceFill([
                'html_source' => $htmlSource,
                'rendered_html_cache' => $this->renderer->renderPreviewHtml($htmlSource, $page->name),
                'status' => 'valid',
                'last_generation_summary' => $document['page_metadata']['prompt_summary'] ?? null,
            ])->save();

            $this->snapshotVersion($page, 'edit', 'Insert: '.$instruction);

            $this->events->record($page, 'insert_applied', 'section_inserter', 'success', $result['explanation'], $eventTargetId, [
                'blocks' => $result['blocks'] ?? [],
                'anchor_id' => $eventTargetId,
                'position' => $position,
                'html_source' => $result['html_source'],
            ] + $this->payloadWithUsage($result));

            return $document;
        } catch (Throwable $exception) {
            $page->forceFill(['status' => 'error'])->save();
            $this->events->record($page, 'insert_rejected', 'section_inserter', 'error', $exception->getMessage(), $eventTargetId);

            throw $exception;
        }
    }

    public function moveSection(Page $page, string $sourceBlockId, string $targetBlockId, string $position): array
    {
        $sourceBlockId = trim($sourceBlockId);
        $targetBlockId = trim($targetBlockId);
        $position = $position === 'before' ? 'before' : 'after';

        if ($sourceBlockId === '' || $targetBlockId === '') {
            throw new HtmlValidationException(['A source and target block id are required to move a section.']);
        }

        if ($sourceBlockId === $targetBlockId) {
            throw new HtmlValidationException(['Cannot move a section relative to itself.']);
        }

        $this->events->record($page, 'move_requested', 'section_mover', 'info', 'Reordering section.', $sourceBlockId, [
            'source_id' => $sourceBlockId,
            'target_id' => $targetBlockId,
            'position' => $position,
        ]);

        try {
            $currentHtml = (string) ($page->html_source ?? '');

            $this->snapshotVersion($page, 'edit', 'Move: '.$sourceBlockId.' '.$position.' '.$targetBlockId);

            $htmlSource = $this->cleanHtml($this->blockIndexer->moveBlock(
                $currentHtml,
                $sourceBlockId,
                $targetBlockId,
                $position,
            ));
            $this->htmlValidator->assertValid($htmlSource);
            $blockIndex = $this->scrubArray($this->blockIndexer->index($htmlSource));
            $document = $this->scrubArray($this->htmlArtifact(
                $this->moveArtifact($page, $sourceBlockId, $targetBlockId, $position),
                $htmlSource,
                $blockIndex,
            ));

            $page->forceFill([
                'html_source' => $htmlSource,
                'rendered_html_cache' => $this->renderer->renderPreviewHtml($htmlSource, $page->name),
                'status' => 'valid',
                'last_generation_summary' => $document['page_metadata']['prompt_summary'] ?? null,
            ])->save();

            $this->events->record($page, 'move_applied', 'section_mover', 'success', 'Reordered section.', $sourceBlockId, [
                'source_id' => $sourceBlockId,
                'target_id' => $targetBlockId,
                'position' => $position,
            ]);

            return $document;
        } catch (Throwable $exception) {
            $page->forceFill(['status' => 'valid'])->save();
            $this->events->record($page, 'move_rejected', 'section_mover', 'error', $exception->getMessage(), $sourceBlockId);

            throw $exception;
        }
    }

    public function removeSection(Page $page, string $blockId): array
    {
        $blockId = trim($blockId);

        if ($blockId === '') {
            throw new HtmlValidationException(['A block id is required to remove a section.']);
        }

        $this->events->record($page, 'remove_requested', 'section_remover', 'info', 'Removing selected section.', $blockId, [
            'block_id' => $blockId,
        ]);

        try {
            $currentHtml = (string) ($page->html_source ?? '');
            $existingBlocks = $this->blockIndexer->index($currentHtml);
            if (count($existingBlocks) <= 1) {
                throw new HtmlValidationException(['Cannot remove the only section on the page.']);
            }

            $this->snapshotVersion($page, 'edit', 'Remove: '.$blockId);

            $htmlSource = $this->cleanHtml($this->blockIndexer->removeBlock($currentHtml, $blockId));
            $this->htmlValidator->assertValid($htmlSource);
            $blockIndex = $this->scrubArray($this->blockIndexer->index($htmlSource));
            $document = $this->scrubArray($this->htmlArtifact(
                $this->removeArtifact($page, $blockId),
                $htmlSource,
                $blockIndex,
            ));

            $page->forceFill([
                'html_source' => $htmlSource,
                'rendered_html_cache' => $this->renderer->renderPreviewHtml($htmlSource, $page->name),
                'status' => 'valid',
                'last_generation_summary' => $document['page_metadata']['prompt_summary'] ?? null,
            ])->save();

            $this->events->record($page, 'remove_applied', 'section_remover', 'success', 'Removed section.', $blockId, [
                'block_id' => $blockId,
                'blocks_remaining' => count($blockIndex),
            ]);

            return $document;
        } catch (Throwable $exception) {
            $page->forceFill(['status' => 'valid'])->save();
            $this->events->record($page, 'remove_rejected', 'section_remover', 'error', $exception->getMessage(), $blockId);

            throw $exception;
        }
    }

    public function enhanceDocument(Page $page, string $instruction, string $summary = 'Enhanced document', ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $summary = trim($summary) !== '' ? trim($summary) : 'Enhanced document';

        if (! $this->hasFreshEnhancementRequestEvent($page, $summary)) {
            $this->events->record($page, 'enhance_requested', 'document_enhancer', 'info', $summary, payload: [
                'instruction' => $instruction,
                'summary' => $summary,
            ]);
        }

        try {
            $result = $this->documentEnhancer->enhance($page, $instruction, $summary, $provider, $model, $apiKey);
            $htmlSource = $this->cleanHtml((string) ($result['html_source'] ?? ''));

            $this->htmlValidator->assertValid($htmlSource);
            $blockIndex = $this->scrubArray($this->blockIndexer->index($htmlSource));
            $document = $this->scrubArray($this->htmlArtifact(
                $this->enhancementArtifact($page, $summary),
                $htmlSource,
                $blockIndex,
            ));

            $this->snapshotVersion($page, 'edit', $summary);

            $page->forceFill([
                'html_source' => $htmlSource,
                'rendered_html_cache' => $this->renderer->renderPreviewHtml($htmlSource, $page->name),
                'status' => 'valid',
                'last_generation_summary' => $document['page_metadata']['prompt_summary'] ?? null,
            ])->save();

            $this->events->record($page, 'enhance_applied', 'document_enhancer', 'success', $result['explanation'], payload: [
                'blocks' => $result['blocks'] ?? [],
                'html_source' => $htmlSource,
                'summary' => $summary,
            ] + $this->payloadWithUsage($result));

            return $document;
        } catch (Throwable $exception) {
            $page->forceFill(['status' => 'error'])->save();
            $this->events->record($page, 'enhance_rejected', 'document_enhancer', 'error', $exception->getMessage(), payload: [
                'summary' => $summary,
            ]);

            throw $exception;
        }
    }

    public function granularizeBlocks(Page $page, ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        return $this->enhanceDocument(
            $page,
            'Add more granular editable tw:block regions around repeated meaningful content such as testimonial cards, feature cards, pricing cards, FAQ rows, stats, logos, gallery items, and CTA groups. Remove any coarse parent block markers when splitting children so no tw:block markers are nested.',
            'Refined editable block markers.',
            $provider,
            $model,
            $apiKey,
        );
    }

    /**
     * @param  array<int, string>  $targetIds
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    public function editMany(Page $page, array $targetIds, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null, array $images = []): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $targetIds = array_values(array_unique(array_filter(
            $targetIds,
            fn (mixed $id): bool => is_string($id) && $id !== '',
        )));
        $eventTargetId = implode(',', $targetIds);

        if (! $this->hasFreshEditRequestEvent($page, $eventTargetId)) {
            $this->events->record($page, 'edit_requested', 'targeted_edit', 'info', count($targetIds) > 1 ? 'Editing selected block range.' : 'Editing selected block.', $eventTargetId, [
                'instruction' => $instruction,
                'target_ids' => $targetIds,
                'reference_images' => count($images),
            ]);
        }

        try {
            $result = $this->targetedEdit->editMany($page, $targetIds, $instruction, $provider, $model, $apiKey, $images);
            $htmlSource = $this->blockIndexer->replaceBlocks(
                (string) ($page->html_source ?? ''),
                $targetIds,
                $result['html_source'],
            );
            $htmlSource = $this->cleanHtml($htmlSource);

            $this->htmlValidator->assertValid($htmlSource);
            $blockIndex = $this->scrubArray($this->blockIndexer->index($htmlSource));
            $document = $this->scrubArray($this->htmlArtifact(
                $this->editArtifact($page, $instruction),
                $htmlSource,
                $blockIndex,
            ));

            $page->forceFill([
                'html_source' => $htmlSource,
                'rendered_html_cache' => $this->renderer->renderPreviewHtml($htmlSource, $page->name),
                'status' => 'valid',
                'last_generation_summary' => $document['page_metadata']['prompt_summary'] ?? null,
            ])->save();

            $this->snapshotVersion($page, 'edit', 'Edit: '.$instruction);

            $this->events->record($page, 'edit_applied', 'targeted_edit', 'success', $result['explanation'], $eventTargetId, [
                'blocks' => $result['blocks'] ?? [],
                'target_ids' => $targetIds,
                'html_source' => $result['html_source'],
            ] + $this->payloadWithUsage($result));

            return $document;
        } catch (Throwable $exception) {
            $page->forceFill(['status' => 'error'])->save();
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

    private function hasFreshEditRequestEvent(Page $page, string $targetId): bool
    {
        $event = $page->generationEvents()
            ->where('kind', 'edit_requested')
            ->where('stage', 'targeted_edit')
            ->latest('occurred_at')
            ->first();

        return $event !== null
            && $event->target_id === $targetId
            && $event->occurred_at !== null
            && $event->occurred_at->greaterThan(now('UTC')->subMinutes(10));
    }

    private function hasFreshInsertRequestEvent(Page $page, ?string $targetId, string $position): bool
    {
        $event = $page->generationEvents()
            ->where('kind', 'insert_requested')
            ->where('stage', 'section_inserter')
            ->latest('occurred_at')
            ->first();

        return $event !== null
            && $event->target_id === $targetId
            && ($event->payload['position'] ?? null) === $position
            && $event->occurred_at !== null
            && $event->occurred_at->greaterThan(now('UTC')->subMinutes(10));
    }

    private function hasFreshEnhancementRequestEvent(Page $page, string $summary): bool
    {
        $event = $page->generationEvents()
            ->where('kind', 'enhance_requested')
            ->where('stage', 'document_enhancer')
            ->latest('occurred_at')
            ->first();

        return $event !== null
            && ($event->payload['summary'] ?? null) === $summary
            && $event->occurred_at !== null
            && $event->occurred_at->greaterThan(now('UTC')->subMinutes(10));
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
            if (str_contains($error, 'script tag')
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
            .'<div>'."\n"
            .$rawHtml."\n"
            .'</div>'."\n"
            .'<!-- /tw:block -->';
    }

    private function editArtifact(Page $page, string $instruction): array
    {
        return [
            'title' => $page->name,
            'page_type' => 'generic',
            'goal' => $page->prompt !== '' ? str($page->prompt)->limit(180)->toString() : 'Edited from builder instruction.',
            'audience' => 'Visitors',
            'prompt_summary' => 'Targeted edit: '.str($instruction)->limit(120),
        ];
    }

    private function insertArtifact(Page $page, string $instruction): array
    {
        return [
            'title' => $page->name,
            'page_type' => 'generic',
            'goal' => $page->prompt !== '' ? str($page->prompt)->limit(180)->toString() : 'Edited from builder instruction.',
            'audience' => 'Visitors',
            'prompt_summary' => 'Inserted section: '.str($instruction)->limit(120),
        ];
    }

    private function moveArtifact(Page $page, string $sourceBlockId, string $targetBlockId, string $position): array
    {
        return [
            'title' => $page->name,
            'page_type' => 'generic',
            'goal' => $page->prompt !== '' ? str($page->prompt)->limit(180)->toString() : 'Edited from builder instruction.',
            'audience' => 'Visitors',
            'prompt_summary' => 'Moved '.$sourceBlockId.' '.$position.' '.$targetBlockId,
        ];
    }

    private function removeArtifact(Page $page, string $blockId): array
    {
        return [
            'title' => $page->name,
            'page_type' => 'generic',
            'goal' => $page->prompt !== '' ? str($page->prompt)->limit(180)->toString() : 'Edited from builder instruction.',
            'audience' => 'Visitors',
            'prompt_summary' => 'Removed section: '.$blockId,
        ];
    }

    private function enhancementArtifact(Page $page, string $summary): array
    {
        return [
            'title' => $page->name,
            'page_type' => 'generic',
            'goal' => $page->prompt !== '' ? str($page->prompt)->limit(180)->toString() : 'Refined from builder instruction.',
            'audience' => 'Visitors',
            'prompt_summary' => $summary,
        ];
    }

    private function insertRequestedSummary(?string $anchorBlockId, string $position): string
    {
        if ($anchorBlockId === null || $anchorBlockId === '') {
            return $position === 'before' ? 'Inserting section at top of page.' : 'Inserting section at end of page.';
        }

        return 'Inserting section '.$position.' selected block.';
    }
}
