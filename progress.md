# Progress Log

> Read `plan.md` first. This file is the mutable session log. Update protocol is defined in `plan.md` Sec. 22.
> Encoding: pure ASCII. Do not introduce non-ASCII characters when editing.

## Current Milestone
M3 - Renderer and Preview

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
- [2026-05-20] M2.dependencies: installed Livewire 4.
- [2026-05-20] M2.routes: added project list, project dashboard, and builder workspace routes.
- [2026-05-20] M2.components: added Livewire 4 folder-based components for the full builder shell and placeholder child panels.
- [2026-05-20] M2.flows: implemented project creation and empty-page creation with draft document JSON.
- [2026-05-20] M2.tests: added builder shell feature coverage for create flows, workspace rendering, preview CSS iframe reference, and stream empty state.
- [2026-05-20] M2.acceptance: `php artisan migrate:fresh --no-interaction`, `php artisan test`, and `npm.cmd run build` pass.

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
- Livewire 4.3.0 is installed for M2 because it is compatible with Laravel 13 and available as the current stable Livewire 4 package.
- Empty pages are persisted as valid draft `Document` JSON with an empty `document_tree`, so M2 can navigate to the workspace before generation exists.
- The shared app layout skips Vite asset resolution in the `testing` environment; otherwise feature tests fail without a built manifest.

## Spec Change Proposals
- None.

## Files Created Or Modified This Session
- `composer.json`: modified: added `livewire/livewire`.
- `composer.lock`: modified: locked Livewire 4.3.0.
- `package-lock.json`: created: npm lockfile from `npm.cmd install`.
- `routes/web.php`: modified: replaced welcome route with M2 Livewire routes.
- `resources/views/components/layouts/app.blade.php`: created: shared app layout.
- `app/Livewire/Projects/ProjectList/ProjectList.php`: created: project list and create flow.
- `app/Livewire/Projects/ProjectList/project-list.blade.php`: created: project list UI.
- `app/Livewire/Projects/ProjectList/project-list.js`: created: component placeholder module.
- `app/Livewire/Projects/ProjectDashboard/ProjectDashboard.php`: created: dashboard and page create flow.
- `app/Livewire/Projects/ProjectDashboard/project-dashboard.blade.php`: created: dashboard UI.
- `app/Livewire/Projects/ProjectDashboard/project-dashboard.js`: created: component placeholder module.
- `app/Livewire/Builder/Workspace/Workspace.php`: created: workspace parent state component.
- `app/Livewire/Builder/Workspace/workspace.blade.php`: created: four-panel workspace layout.
- `app/Livewire/Builder/Workspace/workspace.js`: created: component placeholder module.
- `app/Livewire/Builder/LeftSidebar/LeftSidebar.php`: created: sidebar shell component.
- `app/Livewire/Builder/LeftSidebar/left-sidebar.blade.php`: created: sidebar composition.
- `app/Livewire/Builder/LeftSidebar/left-sidebar.js`: created: component placeholder module.
- `app/Livewire/Builder/Canvas/Canvas.php`: created: canvas component.
- `app/Livewire/Builder/Canvas/canvas.blade.php`: created: placeholder iframe with `preview.css`.
- `app/Livewire/Builder/Canvas/canvas.js`: created: component placeholder module.
- `app/Livewire/Builder/RightInspector/RightInspector.php`: created: inspector shell component.
- `app/Livewire/Builder/RightInspector/right-inspector.blade.php`: created: inspector composition.
- `app/Livewire/Builder/RightInspector/right-inspector.js`: created: component placeholder module.
- `app/Livewire/Builder/StreamPanel/StreamPanel.php`: created: stream shell component.
- `app/Livewire/Builder/StreamPanel/stream-panel.blade.php`: created: stream panel composition.
- `app/Livewire/Builder/StreamPanel/stream-panel.js`: created: component placeholder module.
- `app/Livewire/Builder/SidePanels/ProjectSwitcher/ProjectSwitcher.php`: created: project switcher placeholder.
- `app/Livewire/Builder/SidePanels/ProjectSwitcher/project-switcher.blade.php`: created: project switcher UI.
- `app/Livewire/Builder/SidePanels/ProjectSwitcher/project-switcher.js`: created: component placeholder module.
- `app/Livewire/Builder/SidePanels/SectionTree/SectionTree.php`: created: section tree placeholder.
- `app/Livewire/Builder/SidePanels/SectionTree/section-tree.blade.php`: created: section tree UI.
- `app/Livewire/Builder/SidePanels/SectionTree/section-tree.js`: created: component placeholder module.
- `app/Livewire/Builder/SidePanels/ElementLibraryPanel/ElementLibraryPanel.php`: created: element library placeholder.
- `app/Livewire/Builder/SidePanels/ElementLibraryPanel/element-library-panel.blade.php`: created: element library UI.
- `app/Livewire/Builder/SidePanels/ElementLibraryPanel/element-library-panel.js`: created: component placeholder module.
- `app/Livewire/Builder/SidePanels/GenerationControls/GenerationControls.php`: created: generation controls placeholder.
- `app/Livewire/Builder/SidePanels/GenerationControls/generation-controls.blade.php`: created: generation controls UI.
- `app/Livewire/Builder/SidePanels/GenerationControls/generation-controls.js`: created: component placeholder module.
- `app/Livewire/Builder/Inspector/NodeSummary/NodeSummary.php`: created: node summary placeholder.
- `app/Livewire/Builder/Inspector/NodeSummary/node-summary.blade.php`: created: node summary UI.
- `app/Livewire/Builder/Inspector/NodeSummary/node-summary.js`: created: component placeholder module.
- `app/Livewire/Builder/Inspector/EditForm/EditForm.php`: created: edit form placeholder.
- `app/Livewire/Builder/Inspector/EditForm/edit-form.blade.php`: created: edit form UI.
- `app/Livewire/Builder/Inspector/EditForm/edit-form.js`: created: component placeholder module.
- `app/Livewire/Builder/Inspector/LockToggles/LockToggles.php`: created: lock toggles placeholder.
- `app/Livewire/Builder/Inspector/LockToggles/lock-toggles.blade.php`: created: lock toggles UI.
- `app/Livewire/Builder/Inspector/LockToggles/lock-toggles.js`: created: component placeholder module.
- `app/Livewire/Builder/StreamPanel/EventList/EventList.php`: created: generation event list placeholder.
- `app/Livewire/Builder/StreamPanel/EventList/event-list.blade.php`: created: generation event list UI.
- `app/Livewire/Builder/StreamPanel/EventList/event-list.js`: created: component placeholder module.
- `tests/Feature/BuilderShellTest.php`: created: M2 feature tests.
- `tests/Feature/ExampleTest.php`: modified: uses `RefreshDatabase` for the new database-backed home route.
- `progress.md`: modified: recorded M2 completion and next M3 handoff.

## Next Up (Top 3)
1. Begin M3: add `Renderer.php`, `TailwindClassMap`, and the render Blade partial structure.
2. M3: replace the canvas placeholder `srcdoc` with rendered fixture document HTML and the preview bridge.
3. M3: add selection postMessage handling so the inspector receives selected node IDs.

## Notes
- Every agent: read `plan.md` Sec. 0.3 (Rules Of Engagement) before touching anything.
- M1 acceptance is complete as of 2026-05-20.
- `php artisan migrate:fresh --no-interaction` required elevated filesystem permission in this environment for SQLite writes.
- `php artisan test` required elevated filesystem permission once for Laravel compiled view writes; final run passed.
- Completed M1 foundations and the requested agent-instruction update are ready to commit after successful verification.
- M2 acceptance is complete as of 2026-05-20.
- `npm run build` is blocked by PowerShell execution policy for `npm.ps1` in this environment; `npm.cmd run build` works and passed.
- If a decision in `plan.md` looks wrong while implementing, follow `plan.md` Sec. 22.5: stop and propose, do not silently change the spec.
- Encoding rule (`plan.md` Sec. 23.7) is non-negotiable for both this file and `plan.md`. Use `->` not an arrow, `Sec.` not a section sign, straight quotes only.
