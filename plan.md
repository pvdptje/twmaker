# Tailwind Website Maker Plan

> Status: living product and architecture plan.
> Last rewritten: 2026-05-25.
> Companion file: `progress.md` is the historical session log. It is useful context, but this file is the current north star.
> Encoding: ASCII only.

---

## 1. Product

Tailwind Website Maker is an internal AI-assisted website builder for generating, editing, and exporting fast Tailwind pages.

The key breakthrough is that the page is plain marked HTML, not a rigid JSON component tree. The AI is free to produce real Tailwind layouts. The builder adds just enough structure through comment markers to make sections selectable, editable, reorderable, removable, streamable, and exportable.

### Core User Loop

1. Create a project.
2. Create one or more pages.
3. Choose a provider/model and optionally attach reference screenshots.
4. Generate a complete Tailwind HTML page.
5. Select blocks in the preview or section tree.
6. Apply targeted edits, insert new sections, reorder sections, remove sections, quick-edit an element, or run full-document enhancements.
7. Download a single page as HTML or the whole project as a zip.

### What V1 Is

- A fast local/internal page builder.
- A prompt-to-Tailwind generator.
- A marked-HTML editing workbench.
- A multi-provider LLM playground with model selection.
- A practical export tool for plain HTML.

### What V1 Is Not

- Not Webflow.
- Not Figma.
- Not a CMS.
- Not a multi-user product.
- Not a hosted publishing platform.
- Not an auth/RBAC system.
- Not a drag-and-drop absolute layout editor.

---

## 2. Current System Summary

The old plan started with a strict JSON document schema and reusable element library. That path has been replaced.

### Current Source Of Truth

`pages.html_source` is the source of truth.

The block outline is derived on demand by `App\Services\Html\BlockIndexer`. The old stored `document_json` and stored `block_index` columns have been removed.

### Current Editing Contract

Editable regions are wrapped in comments:

```html
<!-- tw:block id="block_hero" type="hero" label="Hero" -->
<section class="...">
  ...
</section>
<!-- /tw:block -->
```

Group wrappers can contain blocks and exist to keep a parent container selectable:

```html
<!-- tw:group id="group_features" type="feature_grid" label="Features" -->
<section class="...">
  <!-- tw:block id="block_feature_1" type="feature_card" label="Feature 1" -->
  <article class="...">...</article>
  <!-- /tw:block -->
</section>
<!-- /tw:group -->
```

Important rule: `tw:block` markers must not be nested. If a parent contains child blocks, the parent must become a `tw:group`.

### Current LLM Architecture

The app uses Prism through `App\Services\Llm\PrismProvider`.

Implemented providers are configured in `config/llm.php`:

- Anthropic
- DeepSeek
- OpenAI
- OpenRouter
- Ollama
- Mistral
- Groq
- xAI
- Gemini
- Perplexity
- Z.ai

Provider API keys are primarily browser-local through the setup and builder UI, with env keys available as server-side fallbacks. Queued jobs that carry keys or image attachments implement `ShouldBeEncrypted`.

### Current UX

The workspace is a four-area builder:

- Left sidebar: project/page controls, generation controls, section tree.
- Canvas: iframe preview with streaming and quick edit.
- Right inspector: selected block summary and targeted edit form.
- Stream panel: persisted events, live chunks, status, usage, and estimated cost where pricing is known.

---

## 3. Hard Decisions

These are the decisions future work should preserve unless deliberately changed.

| ID | Decision | Why |
|---|---|---|
| D1 | Marked Tailwind HTML is the source of truth. | Keeps generation high-quality and avoids fighting the model with a brittle layout schema. |
| D2 | Block indexes are derived, not persisted. | Comments are simpler, durable, and impossible to desync from stored JSON. |
| D3 | `tw:block` comments define replaceable units. | They give reliable substring replacement without a full DOM editor backend. |
| D4 | `tw:group` is allowed for selectable parent wrappers. | It supports granular editability without nested blocks. |
| D5 | The renderer may wrap generated HTML, but must not own the document model. | The page should remain exportable plain HTML. |
| D6 | Streaming is first-class. | Users need feedback during long model calls and edits. |
| D7 | Provider/model choice is user-facing. | Different models are useful for speed, cost, visual quality, and vision. |
| D8 | Reference images are part of generation/edit/insert. | Screenshots are the fastest way to steer design. |
| D9 | Versions snapshot previous HTML before destructive or AI edits. | Undo/history is not full-featured yet, but recoverability matters. |
| D10 | Legacy JSON rendering stays only for compatibility tests. | Do not build new features on the old schema path. |

Do not revive these old assumptions:

- Strict JSON document tree as primary page storage.
- `reusable_elements` as an active generation dependency.
- Persisted `block_index` on pages.
- Lock controls in the inspector.
- A fixed section vocabulary that rejects creative Tailwind layouts.

---

## 4. Architecture Map

### Routes

| Route | Purpose |
|---|---|
| `/` | Project list |
| `/setup/llm` | Browser-local LLM setup |
| `/projects/{project}` | Project dashboard and page list |
| `/projects/{project}/download-html` | Download generated project pages as a zip |
| `/projects/{project}/pages/{page}` | Builder workspace |
| `/projects/{project}/pages/{page}/download-html` | Download one page as HTML |

### Main Backend Areas

| Area | Files |
|---|---|
| Pipeline orchestration | `app/Services/Generation/Pipeline.php` |
| Page generation | `app/Services/Generation/Stages/SectionGenerator.php` |
| Marker fallback | `app/Services/Generation/Stages/HtmlMarker.php`, `app/Services/Html/DeterministicBlockMarker.php` |
| Targeted edit | `app/Services/Generation/Stages/TargetedEdit.php` |
| Section insertion | `app/Services/Generation/Stages/SectionInserter.php` |
| Full document enhancement | `app/Services/Generation/Stages/DocumentEnhancer.php` |
| Related page prompting | `app/Services/Generation/RelatedPagePromptBuilder.php` |
| HTML indexing | `app/Services/Html/BlockIndexer.php` |
| HTML validation | `app/Services/Html/HtmlDocumentValidator.php` |
| HTML repair/sanitizing | `app/Services/Html/HtmlFragmentRepairer.php`, `app/Services/Html/HtmlSafetySanitizer.php` |
| Quick element edits | `app/Services/Html/QuickElementEditor.php` |
| Preview/export wrapping | `app/Services/Rendering/Renderer.php` |
| LLM provider layer | `app/Services/Llm/*` |

### Jobs

| Job | Purpose |
|---|---|
| `GeneratePageJob` | Full page generation |
| `TargetedEditJob` | Replace one block or a contiguous block range |
| `InsertSectionJob` | Insert one new block before/after an anchor |
| `EnhanceDocumentJob` | Rewrite the full document for editability, color refresh, or custom refinement |
| `GenerateRelatedPageJob` | Generate a sibling page using source page style/header/footer context |
| `GranularizeBlocksJob` | Compatibility wrapper around document enhancement |

### Frontend

| Area | Files |
|---|---|
| Workspace shell | `app/Livewire/Builder/Workspace/*` |
| Canvas | `app/Livewire/Builder/Canvas/*`, `resources/js/builder-canvas.js` |
| Preview bridge | `public/preview-bridge.js` |
| Realtime stream | `resources/js/builder-realtime.js` |
| Image attachments | `resources/js/builder-attachments.js` |
| Section tree | `app/Livewire/Builder/SidePanels/SectionTree/*` |
| Generation controls | `app/Livewire/Builder/SidePanels/GenerationControls/*` |
| Inspector edit form | `app/Livewire/Builder/Inspector/EditForm/*` |
| Model selector | `app/Livewire/Builder/ModelSelector/*` |

---

## 5. Data Model

### Projects

`projects`

- `id`
- `name`
- timestamps

### Pages

`pages`

- `id`
- `project_id`
- `name`
- `prompt`
- `html_source`
- `rendered_html_cache`
- `status`: `draft`, `generating`, `valid`, `invalid`, `error`
- `last_generation_summary`
- timestamps

Only `html_source` is the page artifact. Derived block indexes are recalculated from comments.

### Page Versions

`page_versions`

- `id`
- `page_id`
- `html_source`
- `created_by_kind`
- `summary`
- `created_at`

Snapshots are taken before meaningful mutations, including generation, targeted edits, insertion, removal, reorder, quick edits, and document enhancements.

### Generation Events

`generation_events`

- `id`
- `page_id`
- `kind`
- `stage`
- `target_id`
- `level`
- `summary`
- `payload`
- `occurred_at`

Events are persisted and broadcast. The stream panel should always be able to reconstruct the recent run after reload.

### Legacy Tables

`reusable_elements` can exist for old compatibility paths but is not part of the current generation system.

---

## 6. HTML Marker Contract

### Required For Valid Pages

- At least one `tw:block`.
- Balanced `tw:block` comments.
- Balanced `tw:group` comments if groups are present.
- Every selectable marker has an `id`.
- Selectable IDs are unique across blocks and groups.
- Blocks are not nested.
- Safe HTML according to `HtmlDocumentValidator`.

### Rejected HTML

- Empty source.
- Inline event handlers such as `onclick`.
- `javascript:` URLs.
- Unsafe scripts.
- Nested `tw:block` markers.
- Duplicate selectable IDs.
- Missing IDs.

External `https` script tags with no inline body are currently allowed, mainly so generated HTML can keep common CDN dependencies when needed.

### Runtime Annotation

The preview bridge reads comments and annotates the next rendered element with runtime-only data attributes. Those attributes are not the saved source of truth.

### Replacement Rules

- Targeted edit can replace one block/group or a contiguous block range.
- Insert returns exactly one new `tw:block` region.
- Move/remove use `BlockIndexer` substring operations.
- Full-document enhancement returns a complete updated marked HTML document.
- After any mutation, the app validates, indexes, renders preview HTML, persists the page, snapshots history, and records terminal events.

---

## 7. Generation And Editing Flows

### Full Page Generation

1. User submits prompt, provider, model, optional API key, optional images.
2. `GeneratePageJob` calls `Pipeline::generate`.
3. `SectionGenerator` streams raw Tailwind HTML through Prism text streaming.
4. Empty output is retried once.
5. If the provider still returns empty content, a deterministic fallback page is created.
6. Existing markers are accepted if present.
7. Otherwise `DeterministicBlockMarker` wraps top-level regions locally.
8. If local marking fails for recoverable marker reasons, `HtmlMarker` may be used as fallback.
9. HTML is repaired, sanitized, validated, indexed, cached for preview, and saved.
10. The stream records progress and terminal success/failure.

### Targeted Edit

1. User selects one block/group or multiple contiguous blocks.
2. User enters edit instruction and optional images.
3. `TargetedEditJob` calls `Pipeline::editMany`.
4. The stage sends selected HTML, surrounding context, block outline, and instructions.
5. Replacement HTML streams into the canvas for immediate visual feedback.
6. Replacement IDs are normalized so identity is preserved where appropriate.
7. The replacement is validated and applied by substring replacement.

### Insert Section

1. User opens row menu and picks insert above/below.
2. User enters instruction and optional images.
3. `InsertSectionJob` calls `Pipeline::insertSection`.
4. The model receives anchor context and existing IDs.
5. The returned block is normalized to a unique ID and inserted before/after the anchor.

### Move Section

1. User drags a row in the section tree.
2. `Pipeline::moveSection` snapshots the page.
3. `BlockIndexer::moveBlock` rewrites the marked source.
4. Moves are refused across different group parents.

### Remove Section

1. User chooses remove from the row menu.
2. The app refuses to remove the only remaining block.
3. The selected region is removed and the page is revalidated.

### Quick Element Edit

1. User double-clicks a rendered element in the preview.
2. The preview bridge sends a block ID plus element path.
3. CodeMirror opens with that element's outer HTML.
4. `QuickElementEditor` replaces one complete element inside the selected block/group.
5. Marker comments are forbidden in quick edit replacement HTML.

### Document Enhancement

Enhancements rewrite the whole marked document. Current presets:

- Make blocks more editable.
- Refresh global color scheme.
- Custom full-document refinement.

Enhancement must preserve valid markers and avoid nested blocks. It is the right path for broad visual passes that are bigger than one selected region.

### Related Pages

`GenerateRelatedPageJob` builds a prompt from a source page, reusing header/footer when found and carrying over the visual system. This lets a project grow beyond a single landing page while keeping brand continuity.

---

## 8. LLM And Model Layer

### Provider Abstraction

`LlmProvider` supports:

- Structured calls.
- Text streaming.
- Structured streaming for Anthropic tool-call style paths.

The current implementation is `PrismProvider`.

### Model Registry

`LlmRegistry` exposes implemented providers and model options. `ProviderModelCatalog` fetches and caches provider model lists where supported. Rejected or stale model IDs are evicted and retried through default model fallback.

### Capabilities

`ModelCapabilities` detects text/image support from provider payloads when available and from model ID patterns when needed. The UI blocks screenshot attachments for models that do not support image input.

### Pricing

`ModelPricing` estimates cost from usage where `config/llm_pricing.php` has known prices. Unknown models still show usage without cost.

Pricing is manual by design because provider pricing APIs are not stable. Keep values narrow and explicit.

### Temperature

The app does not force a temperature. Providers use model defaults.

---

## 9. Streaming And Realtime

Realtime uses Laravel Reverb/Echo.

Two event types matter:

- `GenerationStreamChunk`: live text/html chunks.
- `GenerationEventBroadcast`: persisted event lifecycle.

Important behavior:

- Full generation streams into the canvas preview shell.
- Targeted edits stream into a temporary replacement range.
- Stream snapshots are throttled client-side and in the server stream buffer to avoid overwhelming SQLite/Reverb/Livewire.
- Terminal events refresh the workspace and preview.
- Large terminal event payloads should avoid full page HTML unless necessary.

---

## 10. Export

### Single Page

`PageHtmlDownloadController` returns one rendered HTML document from `Renderer::renderDownloadHtml`.

### Project Zip

`ProjectHtmlDownloadController` returns a zip of every generated page in the project. Filenames are slugged and deduplicated.

### Export Principle

Export should be plain HTML that works outside the builder. It may include Tailwind CDN and Alpine CDN. It must not include the preview bridge, selection overlays, or builder-only behavior.

Future hardening can strip marker comments for public export if desired. Keeping comments in downloads is acceptable while the main use case is iterative handoff and re-importable HTML.

---

## 11. Legacy Compatibility

The legacy schema and renderer still exist:

- `app/Services/Schema/*`
- `app/Services/Rendering/Renderer::renderDocument`
- `resources/views/render/sections/*`
- `resources/views/render/nodes/*`
- `resources/views/render/elements/*`
- related tests

Keep this path working enough for tests, but do not expand it for new builder features. New features should operate on marked HTML.

---

## 12. Verification

Common verification commands:

```powershell
vendor\bin\pint.bat --dirty
php artisan test
npm.cmd run test:js
npm.cmd run build
```

Use targeted tests while developing:

```powershell
php artisan test tests\Feature\BuilderShellTest.php
php artisan test tests\Feature\Generation\PipelineTest.php
php artisan test tests\Unit\Html\BlockIndexerTest.php
php artisan test tests\Unit\Llm\PrismProviderTest.php
```

PowerShell may block `npm.ps1`; use `npm.cmd`.

For frontend or preview changes, browser verification matters. Check:

- The iframe is not blank.
- Selection outline follows selected elements.
- Double-click quick edit opens the intended element.
- Streaming generation shows progressive content.
- Targeted edit streaming does not leave stale placeholders.
- Section tree selection and canvas selection stay in sync.
- Mobile and desktop preview remain usable.

---

## 13. Current Quality Bar

A change is done when:

- It preserves `html_source` as source of truth.
- It validates marked HTML after mutation.
- It records useful stream events for AI operations.
- It snapshots prior HTML for meaningful edits.
- It does not push full HTML through Livewire public state unless unavoidable.
- It handles sync queue mode without surfacing Livewire 500 overlays for expected LLM failures.
- It keeps generated page UX responsive.
- It has focused tests for the behavior touched.

---

## 14. Near-Term Plan

### P0: Keep The Fast Builder Feeling

The current system feels fast because preview updates are incremental, Livewire state is slim, and streams are throttled. Preserve that. Any feature that pushes full HTML through Livewire state, bloats broadcast payloads, or remounts the iframe unnecessarily should be treated as a regression.

### P1: Stale Selection And Malformed Edit UX

Improve inspector handling when:

- The selected block was removed.
- A targeted edit returns malformed HTML.
- A selected range is no longer contiguous.
- A group/block distinction changes after enhancement.

The user should see a calm, actionable message and the UI should clear or repair stale selection state.

### P2: Enhancement Completion Browser Pass

Manually verify enhancement completion in the browser without a hard refresh:

- Editability enhancement.
- Color scheme enhancement.
- Custom enhancement.
- Terminal events with small payloads.
- Preview refresh and section tree refresh.

### P3: Version History UX

Version snapshots exist. The next useful layer is a restore UI:

- Show version summaries.
- Preview a version.
- Restore a version into `pages.html_source`.
- Snapshot the current version before restore.

### P4: Export Polish

Decide whether public export should strip marker comments by default, or offer both:

- Builder-preserving HTML with comments.
- Clean public HTML without comments.

Also consider optional CSS inlining or a no-CDN export later.

### P5: Model Cost Coverage

Expand `config/llm_pricing.php` only for models we actually use. Keep unknown models graceful. Do not guess prices casually.

### P6: Related Page Workflow

Make related page generation more visible in the UI:

- Generate "About", "Pricing", "Contact", or custom sibling page from an existing page.
- Preserve header/footer where useful.
- Keep style continuity.

### P7: Prompt And Provider Hardening

Continue hardening around provider quirks:

- Empty output.
- Bad UTF-8.
- Code fences.
- Model not found.
- Provider-specific model capability payloads.
- Long responses that need high token budgets.

---

## 15. File Ownership Hints

When changing builder behavior, start here:

- Selection or preview: `public/preview-bridge.js`, `resources/js/builder-canvas.js`, `Workspace.php`.
- Section tree actions: `SectionTree.php`, `Pipeline.php`, `BlockIndexer.php`.
- AI edits: `TargetedEdit.php`, `targeted_edit.system.md`, `TargetedEditJob.php`.
- Full generation: `SectionGenerator.php`, `section_generator.system.md`, `Pipeline::generate`.
- Full-document passes: `DocumentEnhancer.php`, `document_enhancer.system.md`.
- Provider/model behavior: `config/llm.php`, `LlmRegistry.php`, `ProviderModelCatalog.php`, `PrismProvider.php`.
- Usage/cost display: `ModelPricing.php`, `config/llm_pricing.php`, stream panel views.

---

## 16. Development Notes

- Use `rg` for searching.
- Use `apply_patch` for manual file edits.
- Do not revert unrelated user changes.
- Keep `plan.md` and `progress.md` ASCII only.
- Prefer existing patterns over new abstractions.
- Keep comments sparse and useful.
- Keep generated/exported HTML plain and portable.
- Treat `progress.md` as an append-heavy historical log, not as a blocker for updating this plan when the user explicitly asks.

---

## 17. End State For V1

V1 is successful when a user can:

- Build a multi-page Tailwind site from prompts and screenshots.
- Iterate quickly with targeted block edits.
- Insert, remove, reorder, and refine sections without losing the rest of the page.
- Use multiple LLM providers and vision-capable models.
- See clear progress while work is happening.
- Recover from previous versions.
- Download usable HTML for one page or a whole project.

That is the product now. Keep leaning into the marked-HTML system. It is the part that made the builder versatile, fast, and genuinely pleasant to use.
