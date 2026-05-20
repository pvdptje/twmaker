# Progress Log

> Read `plan.md` first. This file is the mutable session log. Update protocol is defined in `plan.md` Sec. 22.
> Encoding: pure ASCII. Do not introduce non-ASCII characters when editing.

## Current Milestone
M2 - Builder Shell

## Status
idle

## Completed Tasks
- [2026-05-20] spec: `plan.md` R1 drafted as canonical V1 specification.
- [2026-05-20] spec: `progress.md` initialized with Sec. 22 protocol shape.
- [2026-05-20] spec: `plan.md` R2 applied. Four corrections locked.
- [2026-05-20] M1.dependencies: installed `opis/json-schema` for schema validation.
- [2026-05-20] M1.config: added `config/builder.php`, `config/llm.php`, and `config/tailwind_map.php` skeletons.
- [2026-05-20] M1.ids: added typed-prefix ULID generator.
- [2026-05-20] M1.database: added V1 project/page/library/event/version migrations and models; removed the default users migration per V1 scope.
- [2026-05-20] M1.schema: implemented document, section, node, element, and validation services.
- [2026-05-20] M1.tests: added positive and negative schema coverage for every section, node, and element type, malformed fixture coverage, and ID column acceptance coverage.
- [2026-05-20] M1.acceptance: `php artisan migrate:fresh --no-interaction`, `php artisan test --filter=Schema`, and `php artisan test` pass.
- [2026-05-20] spec: added explicit agent instruction to commit completed verified work after updating `progress.md`.

## In Progress
- None.

## Blocked
- None.

## Decisions Made This Session
- All five original open questions resolved and locked in `plan.md` Sec. 3 (queued jobs + Reverb for streaming; Anthropic Claude as first LLM provider with `claude-sonnet-4-5`; srcdoc iframe + postMessage for preview selection; full JSON schema and prop contracts defined).
- R2 corrections applied to `plan.md`:
  - ID DB columns widened from string(26) to string(32). Typed-prefix ULIDs can be up to 31 characters; string(32) accommodates them with a small headroom. See `plan.md` Sec. 4.2 and Sec. 15.
  - Reusable element library demoted from embedded document field to single canonical DB store (`reusable_elements`). Documents reference definitions by `library_id` only. The orchestrator loads the project library into pipeline and renderer context per call. Removes dual-source-of-truth drift. See `plan.md` Sec. 4.5 and Sec. 7.3.
  - Content Principle stated and applied: all visible section text moved out of section props and into child nodes. Affects `logo_cloud`, `feature_grid`, `stats_band`, `testimonial_grid`, `faq`, `contact_form`. See `plan.md` Sec. 5.1.1 and the per-section updates in Sec. 5.2.
  - Both files normalized to pure ASCII to avoid encoding-related friction in any future tooling. See `plan.md` Sec. 23.7.
- M1 schema validation uses `opis/json-schema` for JSON Schema checks and a small semantic pass for rules that need cross-node context, such as ordered section children and count rules.
- Empty JSON object fields are normalized before `opis/json-schema` validation so PHP empty arrays can represent `{}` for fields like `props`, `overrides`, and `payload`.
- The default Laravel users migration was removed because V1 explicitly has no users table.

## Spec Change Proposals
- None.

## Files Created Or Modified This Session
- `plan.md`: modified: added explicit instruction for agents to commit completed verified work after updating `progress.md`.
- `progress.md`: created/rewritten: initial session log with R2 changes recorded.
- `composer.json`: modified: added `opis/json-schema`.
- `composer.lock`: modified: locked `opis/json-schema` and its dependencies.
- `config/builder.php`: created: builder limits and retention skeleton.
- `config/llm.php`: created: provider/model configuration skeleton.
- `config/tailwind_map.php`: created: Tailwind token skeleton.
- `app/Services/Ids/IdGenerator.php`: created: typed-prefix ULID generator.
- `app/Services/Schema/DocumentSchema.php`: created: top-level document JSON Schema.
- `app/Services/Schema/SectionSchemas.php`: created: section envelope and prop schemas.
- `app/Services/Schema/NodeSchemas.php`: created: node envelope and prop schemas.
- `app/Services/Schema/ElementSchemas.php`: created: reusable element definition and prop schemas.
- `app/Services/Schema/SchemaValidator.php`: created: JSON Schema plus semantic validator.
- `app/Services/Schema/SchemaValidationException.php`: created: validation exception wrapper.
- `app/Models/Project.php`: created: project model.
- `app/Models/Page.php`: created: page model.
- `app/Models/ReusableElement.php`: created: reusable element model.
- `app/Models/GenerationEvent.php`: created: generation event model.
- `app/Models/PageVersion.php`: created: page version model.
- `database/migrations/0001_01_01_000000_create_users_table.php`: deleted: V1 has no users table.
- `database/migrations/2026_05_20_000001_create_projects_table.php`: created: projects table.
- `database/migrations/2026_05_20_000002_create_pages_table.php`: created: pages table.
- `database/migrations/2026_05_20_000003_create_reusable_elements_table.php`: created: reusable elements table.
- `database/migrations/2026_05_20_000004_create_generation_events_table.php`: created: generation events table.
- `database/migrations/2026_05_20_000005_create_page_versions_table.php`: created: page versions table.
- `tests/Unit/Schema/SchemaValidatorTest.php`: created: schema coverage for every V1 vocabulary type.
- `tests/Feature/DatabaseSchemaTest.php`: created: ID column acceptance test.
- `tests/fixtures/documents/invalid-missing-document-tree.json`: created: malformed fixture for validator rejection.

## Next Up (Top 3)
1. Begin M2: install and configure Livewire 4 if it is not already present.
2. M2: add project list, project dashboard, and builder workspace routes/components with placeholder content.
3. M2: implement project create and page create flows with empty-page documents.

## Notes
- Every agent: read `plan.md` Sec. 0.3 (Rules Of Engagement) before touching anything.
- M1 acceptance is complete as of 2026-05-20.
- `php artisan migrate:fresh --no-interaction` required elevated filesystem permission in this environment for SQLite writes.
- `php artisan test` required elevated filesystem permission once for Laravel compiled view writes; final run passed.
- Completed M1 foundations and the requested agent-instruction update are ready to commit after successful verification.
- If a decision in `plan.md` looks wrong while implementing, follow `plan.md` Sec. 22.5: stop and propose, do not silently change the spec.
- Encoding rule (`plan.md` Sec. 23.7) is non-negotiable for both this file and `plan.md`. Use `->` not an arrow, `Sec.` not a section sign, straight quotes only.
