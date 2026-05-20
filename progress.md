# Progress Log

> Read `plan.md` first. This file is the mutable session log. Update protocol is defined in `plan.md` Sec. 22.
> Encoding: pure ASCII. Do not introduce non-ASCII characters when editing.

## Current Milestone
M4 - LLM Provider and Generation Pipeline

## Status
in_progress

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
- [2026-05-20] M2.dependencies: installed Livewire 4.
- [2026-05-20] M2.routes: added project list, project dashboard, and builder workspace routes.
- [2026-05-20] M2.components: added Livewire 4 folder-based components for the full builder shell and placeholder child panels.
- [2026-05-20] M2.flows: implemented project creation and empty-page creation with draft document JSON.
- [2026-05-20] M2.tests: added builder shell feature coverage for create flows, workspace rendering, preview CSS iframe reference, and stream empty state.
- [2026-05-20] M2.acceptance: `php artisan migrate:fresh --no-interaction`, `php artisan test`, and `npm.cmd run build` pass.
- [2026-05-20] M1.database-fix: added the standard `sessions` table migration required by local database-backed sessions.
- [2026-05-20] M3.renderer: added `Renderer`, `TailwindClassMap`, render Blade partials for V1 sections/nodes/elements, preview CSS, and preview bridge.
- [2026-05-20] M3.canvas: replaced the placeholder iframe with renderer-generated `srcdoc` and wired preview `node-selected` postMessage events into workspace selection state.
- [2026-05-20] M3.srcdoc-fix: fixed iframe `srcdoc` escaping so rendered HTML is parsed by the iframe instead of shown as plain text.
- [2026-05-20] M3.tests: added renderer unit tests and workspace feature tests for rendered fixture HTML, preview bridge inclusion, safe class rejection, and Livewire selection events.
- [2026-05-20] M3.bridge-tests: added JS DOM coverage for preview bridge click selection, selection overlay class toggling, parent postMessage payloads, and replace-subtree behavior.
- [2026-05-20] M3.preview-css-pipeline: added `npm run build:preview-css`, Tailwind CLI generation for `public/preview.css`, and a single shared safelist source for CSS output and renderer debug assertions.
- [2026-05-20] M3.selection-sync: added parent-to-preview selection sync so iframe highlights can be restored after Livewire updates.
- [2026-05-20] M3.selection-events: changed preview click handling to dispatch `node-selected` through Livewire globally, avoiding stale Canvas child component references after the first selection.
- [2026-05-20] M3.inspector-reactivity: marked right inspector selected-node props reactive so nested inspector components update across repeated canvas selections.
- [2026-05-20] M3.browser-verified-selection: user verified in Herd that switching iframe nodes updates the inspector repeatedly.
- [2026-05-20] M3.acceptance: `php artisan test`, `npm.cmd run test:js`, `npm.cmd run build:preview-css`, and `npm.cmd run build` pass; M3 is complete.
- [2026-05-20] M4.dependencies: installed the official Anthropic PHP SDK package `anthropic-ai/sdk`.
- [2026-05-20] M4.llm-contracts: added `LlmProvider`, `StructuredRequest`, `StructuredResponse`, and `AnthropicProvider` with tool-use structured output.
- [2026-05-20] M4.events: added generation event recording and `GenerationEventBroadcast`; stream event list now polls persisted events.
- [2026-05-20] M4.pipeline-scaffold: added project library loading, prompt loading, generation pipeline, core stage scaffolds, and prompt files.
- [2026-05-20] M4.generation-controls: wired the Generate button to persist prompt/status and dispatch `GeneratePageJob`.
- [2026-05-20] M4.tests: added structured request and fake-provider pipeline tests; `php artisan test`, `npm.cmd run test:js`, and `npm.cmd run build` pass.
- [2026-05-20] M4.stream-dom-cap: added Alpine pruning to cap browser-rendered generation event rows while preserving persisted event history.
- [2026-05-20] M4.validation-hardening: schema validator now reports malformed section column counts instead of throwing PHP type errors; pipeline attempts deterministic repair for recoverable column-count drift.
- [2026-05-20] M4.browser-sync-ux: sync queue generation failures no longer surface as Livewire 500 overlays; stream status now derives from page status and prompt guidance avoids impossible fresh-library sections.
- [2026-05-20] M4.schema-fallback-hardening: unknown node/element prop schemas now use a valid boolean false schema so Opis reports validation failure instead of throwing a schema-engine exception.
- [2026-05-20] M4.assembler-normalization: assembler now assigns server IDs/envelopes, normalizes common section/node aliases, and reorders/fills supported section children before validation.

## In Progress
- None.
- Started: 2026-05-20
- Last activity: 2026-05-20
- Files touched: app/Services/Generation/Stages/Assembler.php, tests/Feature/Generation/PipelineTest.php, progress.md
- Current state: Browser generation failed with many section/node schema errors from conceptual LLM output. Assembler now normalizes supported sections (`hero`, `feature_split`, `faq`, `logo_cloud`, `footer`) and common node aliases before validation. Unsupported element-heavy sections are dropped for now when no safe assembly path exists.

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
- Livewire 4.3.0 is installed for M2 because it is compatible with Laravel 13 and available as the current stable Livewire 4 package.
- Empty pages are persisted as valid draft `Document` JSON with an empty `document_tree`, so M2 can navigate to the workspace before generation exists.
- The shared app layout skips Vite asset resolution in the `testing` environment; otherwise feature tests fail without a built manifest.
- Local `.env` uses `SESSION_DRIVER=database`, so the standard `sessions` table must exist even though V1 has no users table.
- M3 renderer uses Blade partials and a service-level class map rather than hand-building HTML in the canvas, keeping export and preview on the same path later.
- Preview bridge currently supports click selection and `replace-subtree` messages in plain JS. Browser-level verification is still pending.
- Blade must escape iframe `srcdoc` exactly once. Double escaping causes the iframe to render the HTML source as visible text.
- Preview bridge tests use Vitest + jsdom because the bridge is framework-independent plain JS. This gives fast DOM behavior coverage without introducing a full browser automation stack yet.
- Preview CSS generation uses `@tailwindcss/cli` through `scripts/build-preview-css.mjs`; the script injects `resources/tailwind/safelist.txt` into `resources/css/preview.css` and writes `public/preview.css`.
- `TailwindClassMap` reads `resources/tailwind/safelist.txt` for debug assertions so the renderer and CSS build share one class allow-list.
- Browser verification will be handled by the user on request; avoid ad hoc browser automation unless explicitly requested.
- JS bridge tests stay as `npm.cmd run test:js` for now instead of being folded into `php artisan test`; revisit when a CI script exists.
- M4 provider code depends on Anthropic's official `anthropic-ai/sdk` package and keeps it behind `LlmProvider` so tests and future providers can swap implementations.
- Stream panel uses polling against persisted `generation_events` as a working baseline before Echo/Reverb live subscription is wired.
- Do not use `wire:stream` for queued generation worker output. Livewire streaming is request-scoped; queued worker updates should continue through persisted events plus broadcast/polling. Alpine can still prune browser-side rows.
- Add deterministic repair before LLM repair is fully implemented so small schema drifts, such as `columns` returned as an array, do not block local browser testing.
- In sync queue mode, `GeneratePageJob` swallows pipeline exceptions because `Pipeline` already records the error event and page status. This keeps local browser testing from showing Laravel's 500 overlay.
- Fresh projects currently have no reusable element library, so prompts must avoid sections that require element instances until default library seeding is implemented.
- Use JSON Schema boolean `false` for impossible fallback schemas. Do not use invalid empty `not` schemas with Opis.
- Assembler owns server-side document hygiene before validation: IDs, locks, metadata, common prop defaults, alias normalization, and child ordering for supported sections.

## Spec Change Proposals
- None.

## Files Created Or Modified This Session
- `app/Services/Rendering/Renderer.php`: created: document, preview, section, node, and element rendering service.
- `app/Services/Rendering/TailwindClassMap.php`: created: renderer class map with dev-mode safelist assertions.
- `resources/views/render/document.blade.php`: created: document render root.
- `resources/views/render/sections/*.blade.php`: created: section partials for the V1 vocabulary.
- `resources/views/render/nodes/*.blade.php`: created: node partials for the V1 vocabulary.
- `resources/views/render/elements/*.blade.php`: created: reusable element partials for the V1 vocabulary.
- `public/preview-bridge.js`: created: iframe click selection and subtree replacement bridge.
- `public/preview.css`: created: minimal preview baseline and selection outline.
- `resources/tailwind/safelist.txt`: created: initial preview safelist placeholder.
- `resources/css/preview.css`: created: Tailwind v4 preview CSS source with safelist injection token and builder selection styles.
- `scripts/build-preview-css.mjs`: created: reads safelist and builds `public/preview.css` through Tailwind CLI.
- `app/Livewire/Builder/Canvas/Canvas.php`: modified: renders iframe `srcdoc` through `Renderer` and dispatches selected node events.
- `app/Livewire/Builder/Canvas/canvas.blade.php`: modified: uses renderer output, listens for preview bridge messages, and syncs selected node state back into the iframe.
- `app/Livewire/Builder/RightInspector/RightInspector.php`: modified: selected node prop is reactive for repeated canvas selections.
- `app/Livewire/Builder/Inspector/NodeSummary/NodeSummary.php`: modified: selected node prop is reactive for repeated canvas selections.
- `app/Livewire/Builder/Inspector/EditForm/EditForm.php`: modified: selected node prop is reactive for repeated canvas selections.
- `app/Livewire/Builder/Inspector/LockToggles/LockToggles.php`: modified: selected node prop is reactive for repeated canvas selections.
- `app/Livewire/Builder/Workspace/Workspace.php`: modified: listens for `node-selected` events.
- `app/Livewire/Builder/Workspace/workspace.blade.php`: modified: passes selected node state into the canvas.
- `public/preview-bridge.js`: modified: accepts parent-driven `select-node` messages to restore iframe highlight.
- `tests/Unit/Rendering/RendererTest.php`: created: renderer and class-map tests.
- `tests/Feature/BuilderShellTest.php`: modified: added rendered preview and selection event coverage.
- `tests/Js/preview-bridge.test.js`: created: preview bridge DOM behavior tests.
- `package.json`: modified: added `build:preview-css` and `test:js` scripts.
- `package-lock.json`: modified: added Tailwind CLI, Vitest, and jsdom dev dependencies.
- `progress.md`: modified: recorded M3 progress and handoff state.
- `composer.json`: modified: added `anthropic-ai/sdk`.
- `composer.lock`: modified: locked Anthropic SDK dependencies.
- `app/Services/Llm/LlmProvider.php`: created: structured LLM provider interface.
- `app/Services/Llm/StructuredRequest.php`: created: stage-aware structured request value object.
- `app/Services/Llm/StructuredResponse.php`: created: structured response value object.
- `app/Services/Llm/AnthropicProvider.php`: created: Anthropic SDK adapter using tool-use structured output.
- `app/Services/Generation/ProjectLibraryLoader.php`: created: full library and digest loader.
- `app/Services/Generation/GenerationEventRecorder.php`: created: persists and broadcasts generation events.
- `app/Events/GenerationEventBroadcast.php`: created: broadcast payload for generation events.
- `app/Services/Generation/Pipeline.php`: created: generation orchestration scaffold.
- `app/Services/Generation/Stages/PromptBuilder.php`: created: runtime prompt file loader.
- `app/Services/Generation/Stages/Planner.php`: created: planner stage wrapper.
- `app/Services/Generation/Stages/SectionGenerator.php`: created: document generation stage wrapper.
- `app/Services/Generation/Stages/ElementResolver.php`: created: library reference resolver.
- `app/Services/Generation/Stages/Assembler.php`: created: assembler placeholder.
- `app/Services/Generation/Stages/Validator.php`: created: schema validator stage wrapper.
- `app/Services/Generation/Stages/Repair.php`: created: repair stage placeholder.
- `app/Services/Generation/Stages/TargetedEdit.php`: created: targeted edit placeholder.
- `app/Jobs/GeneratePageJob.php`: created: queued generation job.
- `app/Jobs/TargetedEditJob.php`: created: queued targeted edit placeholder for M5.
- `app/Providers/AppServiceProvider.php`: modified: binds `LlmProvider` to the Anthropic adapter.
- `app/Livewire/Builder/SidePanels/GenerationControls/GenerationControls.php`: modified: persists prompts and dispatches generation jobs.
- `app/Livewire/Builder/SidePanels/GenerationControls/generation-controls.blade.php`: modified: enabled Generate button and validation display.
- `app/Livewire/Builder/StreamPanel/EventList/event-list.blade.php`: modified: polls persisted events.
- `resources/prompts/planner.system.md`: created: planner prompt scaffold.
- `resources/prompts/section_generator.system.md`: created: section/document generator prompt scaffold.
- `resources/prompts/repair.system.md`: created: repair prompt scaffold.
- `resources/prompts/targeted_edit.system.md`: created: targeted edit prompt scaffold.
- `tests/Unit/Llm/StructuredRequestTest.php`: created: structured request tool definition coverage.
- `tests/Feature/Generation/PipelineTest.php`: created: fake-provider pipeline coverage.
- `tests/Feature/BuilderShellTest.php`: modified: added generation enqueue coverage.
- `app/Livewire/Builder/StreamPanel/EventList/event-list.blade.php`: modified: added Alpine DOM pruning cap for generation event rows.
- `tests/Feature/BuilderShellTest.php`: modified: asserts stream panel includes the DOM cap behavior.
- `app/Services/Schema/SchemaValidator.php`: modified: makes stats/footer column-count validation type-safe.
- `app/Services/Generation/Pipeline.php`: modified: retries validation once through the repair stage.
- `app/Services/Generation/Stages/Repair.php`: modified: repairs malformed footer/stats `columns` values deterministically.
- `tests/Unit/Schema/SchemaValidatorTest.php`: modified: covers malformed column-count validation without type errors.
- `tests/Feature/Generation/PipelineTest.php`: modified: covers deterministic repair of malformed footer columns.
- `app/Jobs/GeneratePageJob.php`: modified: prevents recorded pipeline failures from bubbling through sync dispatch.
- `app/Livewire/Builder/SidePanels/GenerationControls/GenerationControls.php`: modified: dispatches generation lifecycle events around job dispatch.
- `app/Livewire/Builder/Workspace/Workspace.php`: modified: listens for generation lifecycle events and refreshes document/status.
- `app/Livewire/Builder/StreamPanel/StreamPanel.php`: modified: derives displayed status from the page row.
- `app/Livewire/Builder/StreamPanel/stream-panel.blade.php`: modified: polls stream status.
- `resources/prompts/planner.system.md`: modified: planner avoids element-instance sections when library types are missing.
- `resources/prompts/section_generator.system.md`: modified: stronger section child-order and empty-library guidance.
- `tests/Feature/Generation/GeneratePageJobTest.php`: created: verifies sync dispatch does not bubble pipeline failures.
- `app/Services/Schema/NodeSchemas.php`: modified: unknown node props return boolean false schema.
- `app/Services/Schema/ElementSchemas.php`: modified: unknown element props return boolean false schema.
- `app/Services/Schema/SchemaValidator.php`: modified: accepts boolean schemas and normalizes data safely.
- `tests/Unit/Schema/SchemaValidatorTest.php`: modified: covers unknown node/element validation without schema-engine exceptions.
- `app/Services/Generation/Stages/Assembler.php`: modified: normalizes conceptual LLM JSON into valid V1 envelopes where possible.
- `tests/Feature/Generation/PipelineTest.php`: modified: covers conceptual LLM JSON assembly before validation.

## Next Up (Top 3)
1. M4: seed default project reusable elements or otherwise generate required element definitions before planning element-heavy sections.
2. M4: wire Echo/Reverb subscription for the stream panel and decide whether a separate Livewire `wire:stream` debug path is useful for sync-only local generation.
3. M4: implement LLM repair retry flow and persist generation history entries into document JSON.

## Notes
- Every agent: read `plan.md` Sec. 0.3 (Rules Of Engagement) before touching anything.
- M1 acceptance is complete as of 2026-05-20.
- `php artisan migrate:fresh --no-interaction` required elevated filesystem permission in this environment for SQLite writes.
- `php artisan test` required elevated filesystem permission once for Laravel compiled view writes; final run passed.
- Completed M1 foundations and the requested agent-instruction update are ready to commit after successful verification.
- M2 acceptance is complete as of 2026-05-20.
- M3 partial verification passed after the `srcdoc` fix: `php artisan test` (86 tests, 104 assertions) and `npm.cmd run build`.
- M3 continuation verification passed: `php artisan test` (86 tests, 104 assertions), `npm.cmd run test:js` (2 tests), `npm.cmd run build:preview-css`, and `npm.cmd run build`.
- M3 final verification passed: `php artisan test` (86 tests, 104 assertions), `npm.cmd run test:js` (3 tests), `npm.cmd run build:preview-css`, and `npm.cmd run build`.
- `npm run build` is blocked by PowerShell execution policy for `npm.ps1` in this environment; `npm.cmd run build` works and passed.
- If a decision in `plan.md` looks wrong while implementing, follow `plan.md` Sec. 22.5: stop and propose, do not silently change the spec.
- Encoding rule (`plan.md` Sec. 23.7) is non-negotiable for both this file and `plan.md`. Use `->` not an arrow, `Sec.` not a section sign, straight quotes only.
- M4 partial verification passed: `php artisan test` (89 tests, 113 assertions), `npm.cmd run test:js` (3 tests), and `npm.cmd run build`.
- M4 stream DOM cap verification passed: `php artisan test --filter=BuilderShellTest` and `npm.cmd run build`.
- M4 validation hardening verification passed: `vendor\bin\pint.bat`, `php artisan test` (91 tests, 120 assertions), `npm.cmd run test:js`, and `npm.cmd run build`.
- M4 browser sync UX verification passed: `vendor\bin\pint.bat`, `php artisan test` (94 tests, 126 assertions), `npm.cmd run test:js`, and `npm.cmd run build`.
- M4 schema fallback hardening verification passed: `vendor\bin\pint.bat`, `php artisan test` (96 tests, 132 assertions), `npm.cmd run test:js`, and `npm.cmd run build`.
- M4 assembler normalization verification passed: `vendor\bin\pint.bat`, `php artisan test` (97 tests, 137 assertions), `npm.cmd run test:js`, and `npm.cmd run build`.
