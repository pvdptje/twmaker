<?php

namespace App\Services\Generation;

use App\Models\Page;
use App\Services\Generation\Stages\Assembler;
use App\Services\Generation\Stages\ElementResolver;
use App\Services\Generation\Stages\Planner;
use App\Services\Generation\Stages\Repair;
use App\Services\Generation\Stages\SectionGenerator;
use App\Services\Generation\Stages\Validator;
use App\Services\Schema\SchemaValidationException;
use Throwable;

class Pipeline
{
    public function __construct(
        private readonly ProjectLibraryLoader $libraries,
        private readonly GenerationEventRecorder $events,
        private readonly Planner $planner,
        private readonly SectionGenerator $sections,
        private readonly ElementResolver $elementResolver,
        private readonly Assembler $assembler,
        private readonly Validator $validator,
        private readonly Repair $repair,
    ) {}

    public function generate(Page $page): array
    {
        $page->forceFill(['status' => 'generating'])->save();
        $this->events->record($page, 'stage_started', 'planner', 'info', 'Planning page structure.');

        try {
            $libraryDigest = $this->libraries->digest($page->project);
            $library = $this->libraries->full($page->project);

            $plan = $this->planner->plan($page, $libraryDigest);
            $this->events->record($page, 'stage_completed', 'planner', 'success', 'Planner produced a page outline.', payload: $plan);

            $this->events->record($page, 'stage_started', 'section_generator', 'info', 'Generating structured document.');
            $document = $this->sections->generate($page, $plan, $libraryDigest);
            $this->events->record($page, 'stage_completed', 'section_generator', 'success', 'Document draft generated.');

            $document = $this->assembler->assemble($document);
            $this->elementResolver->assertReferencesResolve($document, $library);
            $document = $this->validateWithRepair($page, $document);

            $page->forceFill([
                'document_json' => $document,
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

    private function validateWithRepair(Page $page, array $document): array
    {
        try {
            $this->validator->assertValidDocument($document);

            return $document;
        } catch (SchemaValidationException $exception) {
            $this->events->record(
                $page,
                'validation_failed',
                'validation',
                'warning',
                'Document failed validation; attempting deterministic repair.',
                payload: ['errors' => $exception->errors],
            );
        }

        $repaired = $this->repair->repairDocument($document, $exception->errors);
        $this->events->record($page, 'repair_attempt', 'repair', 'info', 'Applied deterministic document repair.');
        $this->validator->assertValidDocument($repaired);

        return $repaired;
    }
}
