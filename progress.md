# Progress Log

> Read `plan.md` first. This file is the mutable session log. Update protocol is defined in `plan.md` Sec. 22.
> Encoding: pure ASCII. Do not introduce non-ASCII characters when editing.

## Current Milestone
M3 - Renderer and Preview

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

## In Progress
- M3 renderer and preview implementation.
- Started: 2026-05-20
- Last activity: 2026-05-20
- Files touched: app/Services/Rendering/Renderer.php, app/Services/Rendering/TailwindClassMap.php, resources/views/render/*, public/preview.css, public/preview-bridge.js, resources/css/preview.css, resources/tailwind/safelist.txt, scripts/build-preview-css.mjs, app/Livewire/Builder/Canvas/*, app/Livewire/Builder/Workspace/Workspace.php, tests/Unit/Rendering/RendererTest.php, tests/Feature/BuilderShellTest.php, tests/Js/preview-bridge.test.js, package.json, package-lock.json, progress.md
- Current state: renderer and iframe preview path are working and tested. JS DOM bridge tests cover click selection, overlay class toggling, postMessage payloads, parent-driven selection sync, and replace-subtree. Preview clicks now dispatch selection events directly to Livewire/Workspace instead of through a Canvas child method. Right inspector and nested inspector controls now receive selected-node changes through reactive props. User verified in Herd that switching nodes in the iframe updates the inspector repeatedly. Preview CSS now builds from the checked-in safelist.

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

## Next Up (Top 3)
1. M3: ask the user to browser-verify the iframe click path and rendered layout when a check is useful.
2. M3: review per-section render layouts against the hand-crafted fixture and polish obvious spacing/structure issues.
3. M3: decide whether `test:js` should be folded into the default CI/test command before closing M3.

## Notes
- Every agent: read `plan.md` Sec. 0.3 (Rules Of Engagement) before touching anything.
- M1 acceptance is complete as of 2026-05-20.
- `php artisan migrate:fresh --no-interaction` required elevated filesystem permission in this environment for SQLite writes.
- `php artisan test` required elevated filesystem permission once for Laravel compiled view writes; final run passed.
- Completed M1 foundations and the requested agent-instruction update are ready to commit after successful verification.
- M2 acceptance is complete as of 2026-05-20.
- M3 partial verification passed after the `srcdoc` fix: `php artisan test` (86 tests, 104 assertions) and `npm.cmd run build`.
- M3 continuation verification passed: `php artisan test` (86 tests, 104 assertions), `npm.cmd run test:js` (2 tests), `npm.cmd run build:preview-css`, and `npm.cmd run build`.
- `npm run build` is blocked by PowerShell execution policy for `npm.ps1` in this environment; `npm.cmd run build` works and passed.
- If a decision in `plan.md` looks wrong while implementing, follow `plan.md` Sec. 22.5: stop and propose, do not silently change the spec.
- Encoding rule (`plan.md` Sec. 23.7) is non-negotiable for both this file and `plan.md`. Use `->` not an arrow, `Sec.` not a section sign, straight quotes only.
