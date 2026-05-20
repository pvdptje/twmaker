# plan.md - Tailwind Template Builder V1 Canonical Specification

> Status: Locked. This document is the single source of truth for V1.
> Companion file: `progress.md` (mutable session log; see Sec. 22 for protocol).
> Schema version: 1.
> Encoding: pure ASCII. Do not introduce non-ASCII characters when editing.

---

## 0. How To Read This Document

### 0.1 Audience
Any developer or AI agent picking up the project. This document assumes no prior conversation context.

### 0.2 Reading Order
1. Read Sec. 1 (Product), Sec. 2 (Decisions Locked), Sec. 3 (Open Questions Resolved). About 3 minutes.
2. Read `progress.md` to see where work currently stands.
3. Read the milestone in Sec. 21 that matches `progress.md`'s "Current Milestone".
4. Drill into the relevant schema (Sec. 5 to Sec. 11) or contract (Sec. 12 to Sec. 15) section for the task at hand.

### 0.3 Rules Of Engagement For Agents
- Never change `plan.md` without an explicit request. It is the locked spec. If you believe a decision must change, document the reasoning in `progress.md` under "Spec Change Proposals" and stop. Wait for human confirmation.
- Always update `progress.md` at the start and end of every working session. See Sec. 22.
- Never invent JSON shapes, prop names, or element types not defined in this file. Unknown types are validation failures (Sec. 20).
- Never skip schema validation in code paths. Validation is required at all generator and editor boundaries.
- Never commit partial work without updating `progress.md`'s "In Progress" and "Next Up" sections.
- Never reintroduce non-ASCII characters into `plan.md` or `progress.md`.

### 0.4 How To Find Things
- Schema for a node type -> Sec. 6
- Schema for a section type -> Sec. 5
- Schema for a reusable element -> Sec. 7
- Edit request and response -> Sec. 14
- LLM call orchestration -> Sec. 10 and Sec. 11
- DB tables -> Sec. 15
- Livewire structure -> Sec. 16
- Acceptance criteria -> Sec. 21

### 0.5 Revision History
- R1 (initial lock): canonical spec drafted.
- R2: ID DB columns widened from string(26) to string(32) to fit typed-prefix ULIDs. Element library demoted from embedded document field to single canonical DB table (`reusable_elements`); orchestrator loads it per call. All visible section text moved from props to child nodes (Content Principle, Sec. 5.1.1). Full file normalized to ASCII.

---

## 1. Product Definition (Locked)

### 1.1 What It Is
An internal AI-assisted Tailwind page builder. A user enters a prompt; the system generates a structured landing page; the user iterates on individual sections or elements without regenerating the whole page; the page exports as plain HTML + Tailwind.

### 1.2 What It Is Not
Not Figma. Not Webflow. Not a CMS. Not a multi-tenant SaaS. Not an image generator.

### 1.3 V1 Success Criteria (Verbatim From Original Spec)
- A user can create a project and generate a full landing page draft from a prompt.
- A user can inspect the generated structure as editable sections and elements.
- A user can select one section or element and request a focused edit without rewriting the entire page.
- A user can save useful generated elements into a local reusable library and reuse them later.
- A user can always see generation progress and streaming output while the system is working.
- A user can export the current page as plain HTML + Tailwind.

### 1.4 Hard Out-Of-Scope For V1
- Authentication, accounts, RBAC.
- Real-time multi-user collaboration.
- Publish or hosting.
- Drag-and-drop layout editing.
- Framework exports beyond plain HTML + Tailwind.
- Cross-project shared libraries.
- AI image generation.
- Theme marketplace.

---

## 2. Decisions Locked

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | Stack: Laravel 13, Blade, Tailwind v4, Livewire 4 multi-file components | Matches team familiarity; Livewire 4 multi-file gives clean component separation. |
| D2 | JSON is the source of truth; HTML is a render artifact | Enables stable, scoped editing. |
| D3 | Hybrid document model: semantic sections at top, lower-level nodes inside | Planner stays predictable, editor stays granular. |
| D4 | Reusable elements are project-scoped | Avoids global complexity in V1. |
| D5 | Generation activity is always visible via a stream panel | Trust and diagnosability. |
| D6 | One-way rendering (JSON -> HTML) | No HTML parser maintenance burden. |
| D7 | Hybrid persistence: relational rows + JSON column for document | Queryable + flexible. |
| D8 | Build behind a provider interface; first impl Anthropic | Future-proof. |
| D9 | No raw JSON editing in main UI | UX clarity; reduces malformed-document risk. |
| D10 | Tailwind class output is constrained to a known safelist | Enables predictable preview CSS + render stability. |
| D11 | Reusable element library has one canonical store (DB table); documents reference by ID only | Removes dual-source-of-truth drift. |
| D12 | All visible content lives in child nodes, not section props | Every visible text is selectable and editable. |

---

## 3. Open Questions Resolved

| # | Question | Resolution |
|---|----------|------------|
| OQ1 | Exact JSON schema fields and nesting rules | Defined in full in Sec. 4 to Sec. 9. |
| OQ2 | Exact prop schema per reusable element type | Defined in Sec. 7. |
| OQ3 | Exact preview selection implementation | `srcdoc` iframe + `postMessage` bridge with `data-node-id`. See Sec. 13. |
| OQ4 | Sync streamed partials vs queued with pushed events | Queued jobs + Laravel Reverb broadcasting. See Sec. 12. |
| OQ5 | Provider and model for first impl | Anthropic Claude via official PHP SDK. Default model `claude-sonnet-4-5` (configurable per stage). Structured output enforced via `tool_use`. See Sec. 11. |

---

## 4. Document JSON Schema - Top Level

### 4.1 Top-Level Shape
```ts
type Document = {
  schema_version: 1;
  page_metadata: PageMetadata;
  design_system: DesignSystem;
  document_tree: SectionNode[];
  generation_history: GenerationHistoryEntry[];
};
```
Note: the reusable element library is NOT embedded in the document. Element instances reference definitions by ID (`library_id`), and the orchestrator loads the project's library from the `reusable_elements` DB table at generation, edit, render, and export time. See Sec. 4.5 and Sec. 15.3.

### 4.2 Identifier Convention
All IDs are typed-prefix ULIDs. A ULID is 26 characters; the prefix adds 4 or 5 more, giving a maximum total length of 31 characters. All DB columns that store IDs are `string(32)` to accommodate this with a small headroom.

| Entity | Prefix | Total Length | Example |
|--------|--------|--------------|---------|
| Project | `proj_` | 31 | `proj_01hk5n8q3d7e6w9p2x4y8r6t3v` |
| Page | `page_` | 31 | `page_01hk...` |
| Page version | `ver_` | 30 | `ver_01hk...` |
| Section node | `sec_` | 30 | `sec_01hk...` |
| Generic node | `node_` | 31 | `node_01hk...` |
| Reusable element definition | `elem_` | 31 | `elem_01hk...` |
| Reusable element instance | `inst_` | 31 | `inst_01hk...` |
| Generation event | `evt_` | 30 | `evt_01hk...` |

IDs are generated server-side. The LLM never invents IDs; if it returns content that requires new IDs (for example, new children created during an edit), the orchestrator assigns them after the response.

### 4.3 PageMetadata
```ts
type PageMetadata = {
  title: string;                          // 1..120 chars
  page_type: "landing" | "pricing" | "about" | "product" | "contact" | "feature" | "generic";
  goal: string;                           // 1..500 chars; "Convince devs to sign up for the beta"
  audience: string;                       // 1..300 chars; "Senior backend engineers at SaaS companies"
  prompt_summary: string;                 // 1..2000 chars; condensed user prompt for re-feeding
  status: "draft" | "generating" | "valid" | "invalid" | "error";
  created_at: string;                     // ISO 8601 UTC
  updated_at: string;                     // ISO 8601 UTC
};
```

### 4.4 DesignSystem
The design system is a constrained token set the renderer maps to Tailwind classes. The LLM may set these but may not invent values outside the enums.

```ts
type DesignSystem = {
  colors: {
    primary: TailwindColor;               // see Sec. 4.4.1
    accent: TailwindColor;
    neutral: TailwindColor;
    background: "white" | "neutral-50" | "neutral-100" | "neutral-900" | "neutral-950";
    foreground: "neutral-900" | "neutral-950" | "neutral-50" | "white";
  };
  typography: {
    heading_family: "sans" | "serif" | "mono";
    body_family: "sans" | "serif" | "mono";
    scale: "compact" | "comfortable" | "generous";
  };
  spacing: {
    density: "compact" | "comfortable" | "generous";
    section_padding: "sm" | "md" | "lg" | "xl";
  };
  radius: "none" | "sm" | "md" | "lg" | "xl" | "2xl" | "full";
  tone: "professional" | "playful" | "technical" | "editorial" | "bold" | "minimal";
  dark_mode: boolean;                     // V1: false always; reserved for V2
};
```

#### 4.4.1 TailwindColor Enum
```
slate | gray | zinc | neutral | stone | red | orange | amber | yellow | lime |
green | emerald | teal | cyan | sky | blue | indigo | violet | purple | fuchsia |
pink | rose
```

### 4.5 Element Library Resolution
The library is canonical in the `reusable_elements` DB table (Sec. 15.3). Pipelines and renderers receive a `ProjectLibrary` value object at runtime:

```ts
type ProjectLibrary = Record<string /* elem_... */, ElementDefinition>;
```

Where the library is needed:
- Planner (Sec. 10.2 Stage 1): receives a slim digest (name + type + summary of default_props) so it can suggest reuse.
- Section generator (Sec. 10.2 Stage 2): receives the same slim digest.
- Element resolver (Sec. 10.2 Stage 3): verifies every instance's `library_id` exists in the library; pure code.
- Renderer (Sec. 13): loads full definitions to render instances.
- Targeted edit (Sec. 10.3): same as above.
- Export (Sec. 19): same as renderer.

Consequence: when a user edits a definition in the library, all instances on every page in the project pick up the new `default_props` on next render. Instance-level `overrides` persist independently and continue to win field-by-field. Promoting overrides into a definition is a deliberate action (Sec. 7.2).

### 4.6 DocumentTree
An ordered array of `SectionNode` objects. Section order is the rendered order. Sections are defined in Sec. 5.

### 4.7 GenerationHistory
An append-only ordered array of `GenerationHistoryEntry` objects. Defined in Sec. 9. Capped at 500 entries in V1; older entries roll off but are preserved in the `generation_events` table (Sec. 15.4).

---

## 5. Section Vocabulary

### 5.1 Common Section Envelope
Every section node has this shape. Type-specific `props` and required `children` are layered on top.

```ts
type SectionNodeBase = {
  id: string;                             // sec_...
  type: SectionType;
  props: SectionPropsCommon & SectionSpecificProps;
  children: ContentNode[];                // see Sec. 6 for ContentNode types
  locks: Locks;                           // see Sec. 8
  metadata: NodeMetadata;                 // see Sec. 6.1
};

type SectionPropsCommon = {
  background: "default" | "neutral" | "inverse" | "accent" | "muted";
  padding: "sm" | "md" | "lg" | "xl";
  max_width: "narrow" | "default" | "wide" | "full";
  alignment: "left" | "center";
};
```

### 5.1.1 Content Principle (Locked)
Any visible text that the user would want to click on the canvas to edit MUST be represented as a child node. Section `props` and node `props` may contain visible content only when the content has a 1:1 relationship with a single existing renderable bounding box (for example, `button.label` is fine because clicking the button selects the button node; `feature_grid.heading_text` is NOT fine because nothing else gives the heading its own selectable box).

Allowed-in-props visible content (enumerated):
- `heading.text`, `text.text`, `link.label`, `button.label`, `badge.label`, `image.alt`, `image.src`, `input.placeholder`, `textarea.placeholder`, `icon.name`, anything inside element instance overrides (Sec. 7).

Everything else visible is a child node.

### 5.2 Section Types (Complete V1 Vocabulary)

For every section: order of children is enforced by the validator. Optional children are positional (if present, they appear in the slot indicated). Children types outside the allow-list are rejected.

#### 5.2.1 `header`
Top navigation bar.
```ts
SectionSpecificProps = {
  variant: "simple" | "with_cta" | "centered";
  sticky: boolean;
};
```
Expected children (in order):
1. One `image` node (logo).
2. One `nav_link_group` element instance.
3. Optional one `cta_group` element instance.

#### 5.2.2 `hero`
Primary above-the-fold section.
```ts
SectionSpecificProps = {
  variant: "centered" | "split_left_image" | "split_right_image" | "background_image";
  image_url: string | null;               // V1: user-provided or placeholder
};
```
Expected children (in order):
1. Optional one `badge` node OR `pill_badge` element instance.
2. One `heading` node (level=1).
3. One `text` node (subtitle).
4. Optional one `cta_group` element instance.
5. Optional one `image` node (only for `split_*` variants if `image_url` is null).

#### 5.2.3 `logo_cloud`
Trust strip with partner or customer logos.
```ts
SectionSpecificProps = {};                // common only
```
Expected children (in order):
1. Optional one `heading` node (level=2).
2. 4..8 `image` nodes.

#### 5.2.4 `feature_grid`
Grid of feature cards.
```ts
SectionSpecificProps = {
  columns: 2 | 3 | 4;
};
```
Expected children (in order):
1. Optional one `heading` node (level=2).
2. Optional one `text` node (subhead).
3. 3..12 `feature_card` element instances.

#### 5.2.5 `feature_split`
Side-by-side feature highlight (text + visual).
```ts
SectionSpecificProps = {
  image_side: "left" | "right";
  image_url: string;
};
```
Expected children (in order):
1. One `heading` node (level=2).
2. One `text` node.
3. Optional one `list` node of feature points.
4. Optional one `cta_group` element instance.

#### 5.2.6 `stats_band`
Row of numeric stats.
```ts
SectionSpecificProps = {
  columns: 2 | 3 | 4;
};
```
Expected children (in order):
1. Optional one `heading` node (level=2).
2. N `stat_card` element instances where N matches `columns`.

#### 5.2.7 `testimonial_grid`
Grid of testimonial cards.
```ts
SectionSpecificProps = {
  columns: 1 | 2 | 3;
};
```
Expected children (in order):
1. Optional one `heading` node (level=2).
2. 1..9 `testimonial_card` element instances.

#### 5.2.8 `faq`
Question and answer list.
```ts
SectionSpecificProps = {
  layout: "single_column" | "two_column";
};
```
Expected children (in order):
1. One `heading` node (level=2) (required).
2. 3..12 Q/A pairs: each pair is one `heading` (level=3, the question) followed by one `text` (the answer).

#### 5.2.9 `cta_band`
Call-to-action strip.
```ts
SectionSpecificProps = {
  variant: "centered" | "split";
};
```
Expected children (in order):
1. One `heading` node (level=2).
2. Optional one `text` node.
3. One `cta_group` element instance.

#### 5.2.10 `contact_form`
Contact form section.
```ts
SectionSpecificProps = {
  submit_endpoint: string | null;         // user fills post-export; not visible content
};
```
Expected children (in order):
1. One `heading` node (level=2) (required).
2. Optional one `text` node (subhead).
3. 2..6 `form_group` nodes, each containing one `text` (label) and one `input` or `textarea`.
4. One `primary_button` element instance (submit; label comes from instance overrides).

#### 5.2.11 `footer`
Page footer.
```ts
SectionSpecificProps = {
  variant: "simple" | "columned";
  columns: 1 | 2 | 3 | 4;
};
```
Expected children (in order):
1. One `image` node (logo).
2. Optional one `text` node (tagline).
3. N `nav_link_group` element instances matching `columns`.
4. Optional one `text` node (copyright).

### 5.3 Section Validation Rules
- The validator enforces "Expected children" per section. Missing required children are validation errors.
- Order of children matters and is enforced (positional slots).
- Children types outside the listed allow-list are rejected.

---

## 6. Node Vocabulary

### 6.1 Common Node Envelope
```ts
type ContentNode = {
  id: string;                             // node_... OR inst_... for element instances
  type: NodeType | "element_instance";
  props: NodeProps;
  children?: ContentNode[];               // only for container-like types
  locks: Locks;
  metadata: NodeMetadata;
};

type NodeMetadata = {
  created_by: "planner" | "generator" | "edit" | "library_instance" | "user";
  created_at: string;                     // ISO 8601
  updated_at: string;                     // ISO 8601
  source_library_id?: string;             // only when type is "element_instance"
};
```

### 6.2 Node Types (Complete V1 Vocabulary)

#### 6.2.1 `container`
Generic flex or block wrapper. Has children.
```ts
props = {
  layout: "block" | "flex_row" | "flex_col";
  gap: "none" | "sm" | "md" | "lg";
  alignment: "start" | "center" | "end" | "stretch";
  justification: "start" | "center" | "end" | "between";
  background: "none" | "neutral" | "accent" | "muted";
  padding: "none" | "sm" | "md" | "lg";
  radius: "none" | "sm" | "md" | "lg" | "xl" | "full";
};
```

#### 6.2.2 `stack`
Vertical flow. Has children.
```ts
props = {
  gap: "none" | "sm" | "md" | "lg" | "xl";
  alignment: "left" | "center" | "right";
};
```

#### 6.2.3 `grid`
N-column grid. Has children.
```ts
props = {
  columns: 1 | 2 | 3 | 4 | 6;
  gap: "sm" | "md" | "lg";
};
```

#### 6.2.4 `heading`
```ts
props = {
  level: 1 | 2 | 3 | 4;
  text: string;                           // 1..200 chars
  alignment: "left" | "center" | "right";
  emphasis: "default" | "muted" | "accent";
};
```

#### 6.2.5 `text`
Paragraph or small inline text block.
```ts
props = {
  text: string;                           // 1..2000 chars
  size: "xs" | "sm" | "base" | "lg" | "xl";
  alignment: "left" | "center" | "right";
  emphasis: "default" | "muted" | "accent";
};
```

#### 6.2.6 `image`
```ts
props = {
  src: string;                            // URL or placeholder token like "placeholder:logo"
  alt: string;
  width: number | null;                   // px; null = responsive
  height: number | null;
  fit: "cover" | "contain" | "none";
  radius: "none" | "sm" | "md" | "lg" | "full";
};
```

#### 6.2.7 `button`
Used for standalone buttons not handled by `primary_button` or `secondary_button` elements.
```ts
props = {
  label: string;
  href: string;
  variant: "primary" | "secondary" | "ghost";
  size: "sm" | "md" | "lg";
};
```

#### 6.2.8 `badge`
```ts
props = {
  label: string;
  tone: "neutral" | "positive" | "warning" | "info" | "accent";
};
```

#### 6.2.9 `link`
Plain text link.
```ts
props = {
  label: string;
  href: string;
  emphasis: "default" | "underline" | "muted";
};
```

#### 6.2.10 `input`
```ts
props = {
  name: string;                           // form field name
  input_type: "text" | "email" | "tel" | "url" | "number";
  placeholder: string;
  required: boolean;
};
```

#### 6.2.11 `textarea`
```ts
props = {
  name: string;
  placeholder: string;
  rows: number;                           // 2..12
  required: boolean;
};
```

#### 6.2.12 `form_group`
Label + control wrapper. Has children: exactly one `text` (label) and one `input` or `textarea`.
```ts
props = {
  layout: "stacked" | "inline";
};
```

#### 6.2.13 `card`
Generic card surface. Has children.
```ts
props = {
  variant: "elevated" | "outlined" | "filled";
  padding: "sm" | "md" | "lg";
};
```

#### 6.2.14 `icon`
Icon by name from a fixed set (V1 ships with Heroicons outline set).
```ts
props = {
  name: string;                           // must be in /resources/icons.json allow-list
  size: "sm" | "md" | "lg" | "xl";
  tone: "default" | "muted" | "accent";
};
```

#### 6.2.15 `list`
Has children of type `list_item` only.
```ts
props = {
  style: "bulleted" | "numbered" | "checked";
};
```

#### 6.2.16 `list_item`
Has children: typically one `text` and optionally one `icon`.
```ts
props = {};                                // empty; structure carries meaning
```

#### 6.2.17 `divider`
```ts
props = {
  weight: "thin" | "medium";
  spacing: "sm" | "md" | "lg";
};
```

### 6.3 Element Instance Node
When `type === "element_instance"`, the node references a definition from the project's library (Sec. 4.5):
```ts
type ElementInstanceNode = {
  id: string;                             // inst_...
  type: "element_instance";
  props: {
    library_id: string;                   // elem_...
    overrides: Partial<ElementProps>;     // per-element prop type, see Sec. 7
  };
  locks: Locks;
  metadata: NodeMetadata;                 // metadata.source_library_id = library_id
};
```

### 6.4 Container Validation
Nodes with `children` (container, stack, grid, card, form_group, list, list_item) must declare an array (may be empty for V1). Leaf nodes must not include `children`.

---

## 7. Reusable Element Vocabulary

### 7.1 ElementDefinition Envelope
```ts
type ElementDefinition = {
  id: string;                             // elem_...
  project_id: string;                     // proj_...
  name: string;                           // user-facing label
  type: ElementType;                      // see Sec. 7.4
  default_props: ElementProps;            // per-type, see Sec. 7.4
  preview_html_cache: string | null;      // optional rendered cache
  created_at: string;
  updated_at: string;
};
```

This is stored in the `reusable_elements` DB table (Sec. 15.3) as the canonical source. It is never embedded in the document JSON.

### 7.2 Element Instance Override Rules
- Instance `overrides` may set any subset of the element's prop fields.
- Render order: `default_props` then `overrides` (overrides win, field by field).
- Promoting overrides to the definition writes `default_props = { ...default_props, ...overrides }`, then clears the overrides on the instance. This is a deliberate user action.

### 7.3 Element Library Rules
- Library is project-scoped (one row per definition in `reusable_elements` keyed by `project_id`).
- Editing the definition immediately affects rendering of all instances across all pages in the project on next render. This is intentional: shared definitions are shared.
- Instance `overrides` are unaffected by definition edits.

### 7.4 Element Types - Prop Contracts (Complete V1)

#### 7.4.1 `primary_button`
```ts
ElementProps = {
  label: string;                          // 1..40 chars
  href: string;
  size: "sm" | "md" | "lg";
  icon: string | null;                    // icon name from icons.json, or null
  icon_position: "leading" | "trailing";
};
```

#### 7.4.2 `secondary_button`
```ts
ElementProps = {
  label: string;
  href: string;
  size: "sm" | "md" | "lg";
  icon: string | null;
  icon_position: "leading" | "trailing";
};
```

#### 7.4.3 `pill_badge`
```ts
ElementProps = {
  label: string;                          // 1..30 chars
  tone: "neutral" | "positive" | "warning" | "info" | "accent";
  leading_icon: string | null;
};
```

#### 7.4.4 `feature_card`
```ts
ElementProps = {
  icon: string | null;
  heading: string;                        // 1..80 chars
  body: string;                           // 1..400 chars
  link: { label: string; href: string } | null;
};
```

#### 7.4.5 `testimonial_card`
```ts
ElementProps = {
  quote: string;                          // 1..500 chars
  author_name: string;                    // 1..80 chars
  author_title: string | null;
  author_avatar_url: string | null;
  rating: 1 | 2 | 3 | 4 | 5 | null;
};
```

#### 7.4.6 `stat_card`
```ts
ElementProps = {
  value: string;                          // e.g. "12,500" or "3x"
  label: string;                          // e.g. "Active users"
  trend: "up" | "down" | "flat" | null;
  trend_label: string | null;             // e.g. "+12% MoM"
};
```

#### 7.4.7 `nav_link_group`
```ts
ElementProps = {
  links: Array<{ label: string; href: string; active: boolean }>; // 1..8 items
  layout: "horizontal" | "vertical";
};
```

#### 7.4.8 `cta_group`
```ts
ElementProps = {
  primary: { label: string; href: string } | null;
  secondary: { label: string; href: string } | null;
  alignment: "left" | "center" | "right";
};
```
At least one of `primary` or `secondary` must be present (validator-enforced).

---

## 8. Lock Model

### 8.1 Lock Object
Every node and every section has:
```ts
type Locks = {
  content_locked: boolean;
  style_locked: boolean;
  layout_locked: boolean;
};
```
Default for all generated nodes: `{ content_locked: false, style_locked: false, layout_locked: false }`.

### 8.2 What Each Lock Prevents
| Lock | Blocks edits to |
|------|-----------------|
| `content_locked` | `text`, `label`, `heading.text`, `quote`, `body`, `src`, `alt`, `href`, `placeholder`, any string content field |
| `style_locked` | `background`, `tone`, `emphasis`, `radius`, `variant`, `size`, `padding`, `gap`, `weight`, `alignment` (visual subset), `density` |
| `layout_locked` | Adding, removing, or reordering children; changing `columns`, `layout`, `image_side`, `variant` (when variant changes structural arrangement) |

### 8.3 Enforcement Points
- Validator rejects edits that mutate locked fields.
- Edit prompt to LLM explicitly states the lock set for the target.
- UI greys out the corresponding controls in the inspector.

### 8.4 Lock Propagation
Locks do not inherit. A locked section does not lock its children. Each node is independently locked.

---

## 9. Generation History Event Shape

```ts
type GenerationHistoryEntry = {
  id: string;                             // evt_...
  occurred_at: string;                    // ISO 8601
  kind: GenerationEventKind;
  stage: PipelineStage;                   // see Sec. 10
  target_id: string | null;               // sec_/node_/inst_ when applicable
  summary: string;                        // <= 200 chars
  payload: Record<string, unknown>;       // freeform; see kind list for shape
  level: "info" | "warning" | "error" | "success";
};

type GenerationEventKind =
  | "planner_started"
  | "planner_proposed_structure"
  | "planner_finished"
  | "section_generation_started"
  | "section_generation_partial"
  | "section_generation_finished"
  | "element_resolution_started"
  | "element_resolution_finished"
  | "assembly_finished"
  | "validation_failed"
  | "validation_succeeded"
  | "repair_attempt"
  | "repair_exhausted"
  | "render_succeeded"
  | "render_failed"
  | "edit_requested"
  | "edit_applied"
  | "edit_rejected";
```

Stage and kind mapping is enforced in the orchestrator (one-to-many).

---

## 10. Generation Pipeline

### 10.1 Pipeline Stages
```ts
type PipelineStage =
  | "planner"
  | "section_generation"
  | "element_resolution"
  | "assembly"
  | "validation"
  | "repair"
  | "render"
  | "targeted_edit";
```

### 10.2 Stage Contracts

#### Stage 1 - Planner
- Input: user prompt, optional preferences (page_type hint, audience hint, tone hint), and the project library digest (loaded from DB; names + types + a one-line summary of default_props).
- Output (must match this schema, enforced via tool_use):
  ```ts
  {
    page_metadata: PageMetadata;          // status="generating"
    design_system: DesignSystem;
    section_briefs: Array<{
      section_type: SectionType;
      intent: string;                     // <= 300 chars
      key_content_hints: string[];        // bullet hints for the generator
      suggested_element_uses: string[];   // names of library elements to consider
    }>;
  }
  ```
- Streamed events: `planner_started`, `planner_proposed_structure` (partials), `planner_finished`.
- Failure mode: if the planner fails twice, abort with status="error".

#### Stage 2 - Section Generator
Runs sequentially, one section at a time, in declared order. Sections may reference earlier design choices; parallelism is a V2 optimization.
- Input: one section brief + full `page_metadata` + `design_system` + the project library digest (names, types, default_props) + brief summaries of already-generated sections.
- Output: one fully-formed `SectionNode` matching Sec. 5 schema.
- Streamed events: `section_generation_started`, `section_generation_partial` (one per chunk), `section_generation_finished`.
- Element references: the generator may include `element_instance` nodes referencing existing `library_id`s. It must NOT invent new library IDs.
- Failure mode: validation failure routes to repair (Stage 6).

#### Stage 3 - Element Resolver
- Input: section JSON with `element_instance` references + project library (full definitions).
- Action: for each instance, verify `library_id` exists in `reusable_elements` for this project. If missing -> validation error. Fill any unsupplied `overrides` with empty object.
- No LLM call. Pure code.

#### Stage 4 - Assembler
- Input: all generated sections + planner output.
- Action: assemble into final `Document` shape. Assign any missing IDs (server-side ULID). Set `page_metadata.status = "valid"` if assembly succeeds.
- No LLM call.

#### Stage 5 - Validator
- Input: assembled `Document` + project library.
- Checks (must all pass):
  1. JSON Schema shape conformance.
  2. All node `type` values are in the V1 vocabulary.
  3. All section `children` arrays match the "Expected children" rules (Sec. 5.2).
  4. All `element_instance.library_id` references resolve to definitions in the project library.
  5. All IDs are unique within the document.
  6. All locks are present on all nodes (default-false if absent).
  7. All `image.src` URLs are well-formed or use the `placeholder:` prefix.
  8. All `icon.name` values exist in `/resources/icons.json`.
- Output: validated `Document` OR list of validation errors with paths.

#### Stage 6 - Repair
- Triggered only on validation failure of a generated section.
- Input: the invalid section JSON, the validation errors with JSON paths, the section brief.
- Action: LLM call asking for a targeted fix to ONLY the invalid paths. Output must conform to the section schema.
- Retry limit: 2 repair attempts per section. After exhaustion, mark the section as failed, mark the page status as "invalid", surface error in stream.
- Streamed events: `repair_attempt`, `repair_exhausted`.

#### Stage 7 - Renderer
- See Sec. 13 for full contract.
- No LLM call.

### 10.3 Targeted Edit Pipeline
- Input: target node ID, edit instruction, edit scope (auto-derived from selection + edit type), full document for context, the target's current JSON, the target's locks, the project library.
- LLM call: asks for the patched JSON for the target node only.
- Validation: validates the patched node against its schema AND against locks (no locked-field mutations).
- Apply: patch is merged into the document in-place by node ID.
- Render: re-render only the affected subtree (renderer supports partial render by node ID, Sec. 13.4).
- Streamed events: `edit_requested`, then either `edit_applied` or `edit_rejected`.

### 10.4 Pipeline Orchestration Code Location
- Orchestrator: `app/Services/Generation/Pipeline.php`
- Stage implementations: `app/Services/Generation/Stages/{Planner,SectionGenerator,ElementResolver,Assembler,Validator,Repair,Renderer,TargetedEdit}.php`
- Jobs: `app/Jobs/GeneratePageJob.php`, `app/Jobs/TargetedEditJob.php`
- Library loading helper: `app/Services/Generation/ProjectLibraryLoader.php` (reads `reusable_elements` rows for a project and returns a `ProjectLibrary` value object).

---

## 11. LLM Provider Abstraction

### 11.1 Interface
```php
namespace App\Services\Llm;

interface LlmProvider
{
    /**
     * Synchronous structured completion. Returns the tool-use result decoded as array.
     */
    public function structuredComplete(StructuredRequest $request): StructuredResponse;

    /**
     * Streaming variant. Yields StreamChunk objects until completion.
     * Final chunk has ->is_final=true and ->final_payload populated.
     */
    public function streamStructuredComplete(StructuredRequest $request): \Generator;
}

final class StructuredRequest
{
    public string $model;                   // e.g. "claude-sonnet-4-5"
    public string $system_prompt;
    public array $messages;                 // [{role, content}, ...]
    public string $tool_name;               // name of the enforced tool
    public array $tool_input_schema;        // JSON Schema for the structured output
    public int $max_tokens;
    public float $temperature;
}
```

### 11.2 First Implementation
- `App\Services\Llm\AnthropicProvider`
- Uses the official Anthropic PHP SDK (`anthropic/sdk`) or HTTP via Laravel HTTP client if SDK is unavailable.
- Default model: `claude-sonnet-4-5` (configurable in `config/llm.php`).
- API key: `ANTHROPIC_API_KEY` env var.
- Structured output: enforced via `tool_use` with `tool_choice: { type: "tool", name: <tool_name> }`.

### 11.3 Model Per Stage (defaults)
| Stage | Model | Notes |
|-------|-------|-------|
| planner | `claude-sonnet-4-5` | Reasoning-heavy. |
| section_generator | `claude-sonnet-4-5` | Structure-heavy. |
| repair | `claude-sonnet-4-5` | Same model; smaller scope. |
| targeted_edit | `claude-sonnet-4-5` | Same model. |

All overridable in `config/llm.php`. Per-stage model selection lives in `LlmProvider` callers, not the provider itself.

### 11.4 Config File
```php
// config/llm.php
return [
    'default_provider' => env('LLM_PROVIDER', 'anthropic'),
    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        ],
    ],
    'stage_models' => [
        'planner' => env('LLM_MODEL_PLANNER', 'claude-sonnet-4-5'),
        'section_generator' => env('LLM_MODEL_SECTION', 'claude-sonnet-4-5'),
        'repair' => env('LLM_MODEL_REPAIR', 'claude-sonnet-4-5'),
        'targeted_edit' => env('LLM_MODEL_EDIT', 'claude-sonnet-4-5'),
    ],
    'limits' => [
        'planner_max_tokens' => 4000,
        'section_max_tokens' => 6000,
        'repair_max_tokens' => 3000,
        'edit_max_tokens' => 4000,
    ],
];
```

---

## 12. Streaming and Eventing

### 12.1 Transport Decision
Queued jobs + Laravel Reverb (WebSockets). Generation runs in a queued job; each stage broadcasts events to a private channel. The Livewire stream-panel component subscribes via Laravel Echo.

### 12.2 Why Not Livewire Streaming Generators
- They block the request lifecycle.
- They don't survive page reload.
- They don't allow multiple stage outputs in flight.
- They don't compose with queued jobs.

### 12.3 Channels
| Channel | Purpose | Visibility |
|---------|---------|------------|
| `pages.{page_id}.generation` | All pipeline events for a page | Private (V1: any session; auth scope is V2) |
| `pages.{page_id}.edits` | Targeted edit events | Private |

V1 has no auth, so "private" channel authorization simply returns `true` in `routes/channels.php`. This is documented and intentional.

### 12.4 Broadcast Event Shape
```php
class GenerationEventBroadcast implements ShouldBroadcast
{
    public function __construct(
        public string $page_id,
        public string $event_id,             // evt_...
        public string $kind,                 // see Sec. 9
        public string $stage,
        public ?string $target_id,
        public string $summary,
        public array $payload,
        public string $level,
        public string $occurred_at
    ) {}

    public function broadcastOn(): array {
        return [new PrivateChannel("pages.{$this->page_id}.generation")];
    }
}
```
Every broadcast also writes a `generation_events` row (Sec. 15.4) for replay/reload.

### 12.5 Reverb Setup
- `composer require laravel/reverb`
- `php artisan reverb:install`
- Run `php artisan reverb:start` alongside `php artisan queue:work`.
- Frontend uses Laravel Echo + Pusher protocol client pointed at Reverb.

### 12.6 Frontend Subscription
The stream-panel Livewire component dispatches to Echo on mount:
```js
Echo.private(`pages.${pageId}.generation`)
    .listen('.GenerationEventBroadcast', (e) => {
        Livewire.dispatch('generation-event-received', { event: e });
    });
```
The Livewire component appends events to its in-memory list (capped at 200 displayed; older fade to a "load history" button that hits the DB).

### 12.7 Queue Configuration
- Queue connection: `redis` (default in V1 dev). Fallback to `database` if Redis is unavailable.
- Queues: `generation` (for `GeneratePageJob`) and `edits` (for `TargetedEditJob`).
- Worker command: `php artisan queue:work --queue=generation,edits`.
- Job timeout: 300s. Failed jobs land in `failed_jobs` and broadcast a final error event.

---

## 13. Renderer and Preview Isolation

### 13.1 Renderer Contract
- Input: validated `Document` JSON + project library (or a subtree by node ID + library).
- Output: HTML string with Tailwind classes.
- Every renderable HTML element corresponds to exactly one node and carries `data-node-id="<id>"`.
- The renderer is deterministic: same JSON + same library -> byte-identical HTML.

### 13.2 Renderer Implementation
- Location: `app/Services/Rendering/Renderer.php`
- One Blade partial per section type: `resources/views/render/sections/{type}.blade.php`
- One Blade partial per node type: `resources/views/render/nodes/{type}.blade.php`
- One Blade partial per element type: `resources/views/render/elements/{type}.blade.php`
- Partials receive `$node` (array, decoded JSON), `$design_system`, and `$library` (when applicable).
- Class lookup: a `TailwindClassMap` service translates token combos (for example, `tone=accent + size=md`) into class strings. Map lives in `config/tailwind_map.php`.

### 13.3 Class Safelist
- The renderer emits ONLY classes that appear in `resources/tailwind/safelist.txt`.
- The safelist is generated from the `TailwindClassMap` plus base classes.
- Preview CSS is precompiled from this safelist via `npm run build:preview-css` to `public/preview.css`.
- Any class emitted that is not in the safelist is a render bug; the renderer asserts in dev.

### 13.4 Partial Render By Node ID
The renderer exposes `renderSubtree(Document $doc, ProjectLibrary $library, string $node_id): string` for targeted edits.

### 13.5 Preview Isolation - `srcdoc` Iframe
- The canvas component renders an `<iframe>` with `srcdoc` containing:
  - `<!doctype html><html><head><link rel="stylesheet" href="/preview.css"><script src="/preview-bridge.js"></script></head><body> ...rendered HTML... </body></html>`
- The iframe is same-origin, so the parent can read/write its DOM if ever needed.
- `preview.css` is the precompiled Tailwind safelist (Sec. 13.3).
- `preview-bridge.js` is a tiny script that:
  1. Listens for `click` events.
  2. Walks up from the click target to nearest `[data-node-id]`.
  3. Calls `window.parent.postMessage({ source: 'preview', type: 'node-selected', id }, '*')`.
  4. Listens for `{ source: 'parent', type: 'highlight', id }` and adds an outline class to that node.

### 13.6 Parent Bridge
- Alpine.js component on the canvas listens to `window.message` events filtered by `source === 'preview'`.
- On `node-selected`, dispatches a Livewire event `node-selected` with the ID.
- The inspector Livewire component listens to `node-selected` and loads the node into its form.

### 13.7 Iframe Reload Strategy
- On full-document changes: rebuild `srcdoc` and reset the iframe.
- On subtree changes: parent sends `postMessage({ source: 'parent', type: 'replace-subtree', id, html })`; the bridge replaces `outerHTML` of the matching `[data-node-id]` element.
- This avoids full reloads on small edits.

### 13.8 Selection Overlay
- The bridge applies the class `tplbuilder-selected` (added to the iframe's inline `<style>` block at bootstrap) to the selected element.
- The class is defined as `outline: 2px solid currentColor; outline-offset: 2px; color: rgb(59 130 246);` and is NOT a Tailwind class. It lives in the iframe's bootstrap style block.

---

## 14. Edit Request and Response Contracts

### 14.1 Edit Request (UI to Backend)
```ts
type EditRequest = {
  page_id: string;
  target_id: string;                      // sec_/node_/inst_/elem_
  scope: "element" | "section" | "document_style" | "library_element";
  surfaces: Array<"content" | "style" | "layout" | "swap">;
  instruction: string;                    // user's free-text instruction
  // Optional structured hints from the inspector:
  hints?: {
    target_field?: string;                // e.g. "props.text"
    desired_value?: unknown;              // if surface=content and user typed directly
  };
};
```

### 14.2 Backend Validation Before LLM Call
1. Resolve `target_id` to a node within the page document (or to an `ElementDefinition` row when `scope === "library_element"`).
2. Read the node's `locks`. Filter `surfaces` to remove any blocked by locks.
3. If `surfaces` is empty after filter -> reject with `edit_rejected` event.
4. Build context: document slim summary (section types + IDs) + target subtree + design system + project library digest.

### 14.3 LLM Edit Output Contract
The LLM is invoked via `tool_use` with this tool input schema:
```ts
type EditResponse = {
  patched_node: ContentNode | SectionNode | ElementDefinition;
  changed_paths: string[];                // JSON pointer paths within the node
  explanation: string;                    // <= 300 chars, shown in stream panel
};
```

### 14.4 Post-LLM Validation
- `patched_node.id` must equal the requested `target_id`.
- `patched_node.type` must equal the original type unless scope is `swap` and the new type is in the same family (for example, button to button variant; not text to grid).
- For each path in `changed_paths`, the corresponding lock must be unset.
- The node must pass schema validation.
- Children IDs that exist in the original must be preserved when content survives. New children get freshly-assigned IDs server-side.

### 14.5 Apply and Render
- Replace the node in the document by ID (or write to `reusable_elements` for library scope).
- Bump `page_metadata.updated_at`.
- Write a new `page_versions` row (Sec. 15.5) with the previous snapshot for undo.
- Re-render the subtree via Sec. 13.4.
- Push a `replace-subtree` postMessage to the iframe.
- Emit `edit_applied` event.

### 14.6 Document-Wide Style Edit
Scope `document_style` is special: it only changes `design_system` fields. The validator confirms only `design_system.*` paths changed. Rendering is full-page.

### 14.7 Library Element Edit
Scope `library_element` edits an `ElementDefinition` row in `reusable_elements`. Because definitions are canonical and not embedded in documents, all instances across all pages will pick up the new `default_props` on next render. No batch update step is required. The UI surfaces a confirmation notice on save indicating how many pages contain instances of this definition.

---

## 15. Persistence Schema

All ID columns are `string(32)` (varchar(32)) to fit typed-prefix ULIDs (Sec. 4.2). All timestamps are `timestampTz` (UTC).

### 15.1 `projects`
| Column | Type | Notes |
|--------|------|-------|
| id | string(32) PK | `proj_*` |
| name | string(120) | |
| description | text nullable | |
| default_design_preferences | json nullable | Seeds `design_system` for new pages |
| created_at, updated_at | timestamps | |

### 15.2 `pages`
| Column | Type | Notes |
|--------|------|-------|
| id | string(32) PK | `page_*` |
| project_id | string(32) FK -> projects.id | onDelete cascade |
| name | string(160) | |
| prompt | text | original user prompt |
| document_json | json | current `Document` |
| rendered_html_cache | longText nullable | cache of full render |
| status | enum | draft/generating/valid/invalid/error |
| last_generation_summary | string(500) nullable | |
| created_at, updated_at | timestamps | |

Indexes: `(project_id)`, `(status)`.

### 15.3 `reusable_elements` (CANONICAL ELEMENT LIBRARY STORE)
| Column | Type | Notes |
|--------|------|-------|
| id | string(32) PK | `elem_*` |
| project_id | string(32) FK | onDelete cascade |
| name | string(120) | |
| type | string(40) | from Sec. 7 vocab |
| default_props | json | matches `ElementDefinition.default_props` |
| preview_html_cache | text nullable | |
| created_at, updated_at | timestamps | |

Indexes: `(project_id, type)`, `(project_id)` for full-library fetch.

### 15.4 `generation_events`
| Column | Type | Notes |
|--------|------|-------|
| id | string(32) PK | `evt_*` |
| page_id | string(32) FK | onDelete cascade |
| kind | string(60) | from Sec. 9 vocab |
| stage | string(40) | from Sec. 10.1 vocab |
| target_id | string(32) nullable | |
| level | string(20) | info/warning/error/success |
| summary | string(500) | |
| payload | json nullable | |
| occurred_at | timestampTz | |

Indexes: `(page_id, occurred_at desc)`.

### 15.5 `page_versions`
| Column | Type | Notes |
|--------|------|-------|
| id | string(32) PK | `ver_*` |
| page_id | string(32) FK | onDelete cascade |
| document_json | json | snapshot |
| created_by_kind | string(40) | e.g. `generation`, `edit`, `manual` |
| created_at | timestampTz | |

Indexes: `(page_id, created_at desc)`.

V1 retains last 25 versions per page (configurable in `config/builder.php`). Older versions are pruned by a daily job.

### 15.6 Standard Laravel Tables
- `jobs`, `failed_jobs`, `cache`, `sessions`: default migrations.
- No `users` table in V1.

---

## 16. Livewire Component Architecture

### 16.1 Top-Level Routes
| Route | Component |
|-------|-----------|
| `GET /` | `Projects\ProjectList` |
| `GET /projects/{project}` | `Projects\ProjectDashboard` |
| `GET /projects/{project}/pages/{page}` | `Builder\Workspace` |

### 16.2 Component Tree (Livewire 4 Multi-File)
```
Builder\Workspace                         (page-level shell)
- Builder\LeftSidebar
  - Builder\SidePanels\ProjectSwitcher
  - Builder\SidePanels\SectionTree
  - Builder\SidePanels\ElementLibraryPanel
  - Builder\SidePanels\GenerationControls
- Builder\Canvas                          (renders srcdoc iframe; Alpine-driven bridge)
- Builder\RightInspector
  - Builder\Inspector\NodeSummary
  - Builder\Inspector\EditForm
  - Builder\Inspector\LockToggles
- Builder\StreamPanel
  - Builder\StreamPanel\EventList
```

### 16.3 Multi-File Component Layout
For each Livewire 4 component, e.g. `Builder\Workspace`:
```
app/Livewire/Builder/Workspace/
- Workspace.php
- workspace.blade.php
- workspace.js                            (Alpine + Echo bindings)
```

### 16.4 Inter-Component Communication
- Selection state lives in `Workspace` (the parent).
- Children dispatch Livewire events: `node-selected`, `edit-requested`, `generation-started`, `library-element-saved`, etc.
- The parent listens and updates shared state via `$dispatch`.
- The stream panel listens directly to Echo (Sec. 12.6).

### 16.5 Shared State
Workspace component public properties:
```php
public string $page_id;
public ?string $selected_node_id = null;
public array $document = [];              // decoded Document JSON
public array $library = [];               // project's reusable_elements (loaded from DB)
public string $generation_status = 'idle'; // idle|running|error
```

---

## 17. File and Folder Layout

```
app/
  Http/
    Controllers/                          (thin; most logic in Livewire)
  Livewire/
    Projects/
      ProjectList/
      ProjectDashboard/
    Builder/
      Workspace/
      LeftSidebar/
      Canvas/
      RightInspector/
      StreamPanel/
      SidePanels/
      Inspector/
  Models/
    Project.php
    Page.php
    ReusableElement.php
    GenerationEvent.php
    PageVersion.php
  Jobs/
    GeneratePageJob.php
    TargetedEditJob.php
  Events/
    GenerationEventBroadcast.php
  Services/
    Llm/
      LlmProvider.php                     (interface)
      AnthropicProvider.php
      StructuredRequest.php
      StructuredResponse.php
    Generation/
      Pipeline.php
      ProjectLibraryLoader.php
      Stages/
        Planner.php
        SectionGenerator.php
        ElementResolver.php
        Assembler.php
        Validator.php
        Repair.php
        TargetedEdit.php
        PromptBuilder.php
    Rendering/
      Renderer.php
      TailwindClassMap.php
    Schema/
      DocumentSchema.php                  (JSON Schema definitions)
      SectionSchemas.php
      NodeSchemas.php
      ElementSchemas.php
      SchemaValidator.php
    Ids/
      IdGenerator.php                     (typed ULIDs)
    Export/
      HtmlExporter.php
config/
  llm.php
  tailwind_map.php
  builder.php                             (limits, retries, version retention)
resources/
  views/
    render/
      document.blade.php
      sections/{type}.blade.php
      nodes/{type}.blade.php
      elements/{type}.blade.php
  tailwind/
    safelist.txt
  icons.json
  prompts/
    planner.system.md
    section_generator.system.md
    repair.system.md
    targeted_edit.system.md
public/
  preview.css                             (built from safelist)
  preview-bridge.js
database/migrations/
  2026_xx_xx_create_projects_table.php
  2026_xx_xx_create_pages_table.php
  2026_xx_xx_create_reusable_elements_table.php
  2026_xx_xx_create_generation_events_table.php
  2026_xx_xx_create_page_versions_table.php
tests/
  Feature/
    Generation/
    Editing/
    Rendering/
  Unit/
    Schema/
    Rendering/
    Llm/
```

---

## 18. Tailwind and CSS Handling

### 18.1 Builder App CSS
- The Laravel app uses Tailwind v4 normally for its own UI chrome.
- Builder UI styles live in `resources/css/app.css`.
- These styles never enter the iframe.

### 18.2 Preview CSS
- Built from `resources/tailwind/safelist.txt`.
- Build command: `npm run build:preview-css`.
- Output: `public/preview.css`.
- Rebuilt automatically when safelist changes; CI fails if not committed.

### 18.3 Safelist Composition
The safelist contains:
- All Tailwind base / preflight.
- All color utilities for the 22 colors in Sec. 4.4.1 across `bg-`, `text-`, `border-`, `ring-` for shades `50`, `100`, `200`, `400`, `500`, `600`, `700`, `800`, `900`, `950`.
- Spacing utilities for steps `0`, `1`, `2`, `3`, `4`, `6`, `8`, `12`, `16`, `20`, `24`, `32`, `48`, `64`.
- Typography utilities mapped from `typography.scale`.
- Layout utilities: flex, grid (1-6 cols), gap, alignment, justification, container.
- Radius: `none`, `sm`, `md`, `lg`, `xl`, `2xl`, `full`.
- Width/height: standard set + `max-w-{prose,4xl,5xl,6xl,7xl,full}`.

Renderer asserts every emitted class is in this set (dev mode).

---

## 19. Export

### 19.1 Export Contract
- Input: a page + the project library (resolved at export time).
- Output: a single HTML file with inlined `<style>` (a copy of `preview.css`) and the document body.
- The export contains:
  - `<!doctype html>` + minimal `<head>` (title from metadata, viewport meta, the CSS).
  - The rendered body HTML stripped of `data-node-id` attributes.
- Export removes the bridge script, selection overlay class, and any builder-only artifacts.

### 19.2 Implementation
- `App\Services\Export\HtmlExporter` consumes a `Page` and returns the HTML string.
- Endpoint: `GET /projects/{project}/pages/{page}/export` returns a response with `Content-Disposition: attachment; filename="{slug}.html"`.

---

## 20. Error Model

### 20.1 Categories
| Category | Example | UX Behavior |
|----------|---------|-------------|
| `LlmError` | API timeout, rate limit | Stream a warning event, retry with backoff, then fail. |
| `SchemaValidationError` | Output doesn't match schema | Route to repair; if exhausted, surface inline error in canvas and stream. |
| `LockViolationError` | Edit tried to mutate locked field | Reject edit; toast in UI. |
| `UnknownVocabularyError` | LLM emitted unknown node type | Repair attempt; if exhausted, fail. |
| `RenderError` | Renderer received malformed JSON | Visible error tile in canvas with node ID. |
| `JobFailure` | Queue worker crashed | Mark status="error", broadcast terminal event. |
| `LibraryReferenceError` | `library_id` does not resolve to a definition | Validation error; fail repair if not resolvable. |

### 20.2 Visibility
All errors broadcast a `generation_event` with `level="error"`. Errors never silently swallow.

---

## 21. Milestones and Acceptance Criteria

Each milestone below has explicit deliverables and acceptance criteria. A milestone is "done" only when ALL its acceptance criteria are met AND `progress.md` reflects the completion.

### M1 - Foundations and Schema
Deliverables:
- `app/Services/Schema/` with all schemas implemented as JSON Schema arrays.
- `SchemaValidator` service using `opis/json-schema` or `justinrainbow/json-schema`.
- `app/Services/Ids/IdGenerator.php` with typed ULID generation.
- `config/builder.php`, `config/llm.php`, `config/tailwind_map.php` skeletons.
- All DB migrations from Sec. 15 (with ID columns sized `string(32)`).
- `Project`, `Page`, `ReusableElement`, `GenerationEvent`, `PageVersion` models.
- Feature tests covering schema validation of all section/node/element types (positive + negative cases per type).

Acceptance:
- `php artisan migrate:fresh` succeeds.
- `php artisan test --filter=Schema` passes with at least 1 positive + 1 negative test per section/node/element type.
- Validator rejects every malformed example in a fixtures folder.
- ID columns are confirmed `string(32)` (a test inserts a prefixed ULID of max length and reads it back unchanged).

### M2 - Builder Shell
Deliverables:
- Routes for project list, project dashboard, builder workspace.
- All Livewire 4 multi-file components from Sec. 16.2, rendering placeholder content.
- Workspace layout: left sidebar / canvas / right inspector / stream panel.
- Project create + page create flows (no generation yet; pages start empty).

Acceptance:
- A user can create a project, create a page, navigate to the workspace, and see the four-panel layout.
- The canvas shows a placeholder iframe with `preview.css` loaded.
- The stream panel shows an empty state.

### M3 - Renderer and Preview
Deliverables:
- `Renderer.php` and all Blade partials in `resources/views/render/`.
- `TailwindClassMap`.
- `public/preview-bridge.js`.
- `preview.css` build pipeline (`npm run build:preview-css`).
- Canvas iframe wiring (srcdoc + bridge + selection postMessage).
- Inspector receives `node-selected` events and displays the node ID/type.

Acceptance:
- Loading a hand-crafted fixture `Document` JSON renders correctly in the iframe.
- Clicking on a rendered node selects it and updates the inspector. This includes section headings (Sec. 5.1.1 Content Principle): clicking a `feature_grid`'s heading selects the heading node, not the section.
- Selection overlay shows on the clicked node.
- Subtree replacement via `replace-subtree` postMessage works.
- Renderer assertion catches a non-safelisted class in dev mode.

### M4 - LLM Provider and Generation Pipeline
Deliverables:
- `LlmProvider` interface + `AnthropicProvider` implementation.
- All stages in `app/Services/Generation/Stages/`.
- `ProjectLibraryLoader`.
- `GeneratePageJob`.
- Prompt files in `resources/prompts/`.
- `GenerationEventBroadcast` + Reverb wiring.
- Stream panel Livewire component listening via Echo, persisting events to DB and displaying live.

Acceptance:
- Submitting a prompt creates a `GeneratePageJob`, runs all stages, and produces a valid `Document` for at least 3 distinct prompts in a manual test (for example, "SaaS landing page", "developer tool", "agency portfolio").
- Stream panel shows planner output, per-section progress, validation events, and final success/failure.
- A deliberate schema-violation injected into a stage triggers repair and shows the repair events.
- All events persist to `generation_events` and survive page reload.

### M5 - Targeted Editing and Reusable Elements
Deliverables:
- `TargetedEdit` stage + `TargetedEditJob`.
- Inspector `EditForm` and `LockToggles`.
- Edit request validation (lock enforcement).
- Save-as-reusable flow (turning an instance subtree into an `ElementDefinition` row).
- Insert-reusable flow (dropping a definition into a section).
- Library edit flow that updates the canonical definition; instances reflect the change on next render.

Acceptance:
- Selecting a section + entering an instruction patches only that section. Other sections' HTML is byte-identical.
- Locking content on a node and asking the LLM to change its text results in an `edit_rejected` event.
- Saving a feature_card instance as a reusable definition appears in the library panel.
- Inserting that definition into another `feature_grid` renders correctly.
- Editing the definition updates instances on every page in the project on next render with no extra user step (definition is canonical).

### M6 - Export and Hardening
Deliverables:
- `HtmlExporter` + export route.
- Test coverage: feature tests for full generation, edit, library, and export flows.
- Failure-mode tests: planner timeout, repair exhaustion, malformed LLM output, locked-field edit, library reference miss.
- Documentation pass on `plan.md` (this file) for any drift.
- `README.md` with run instructions.

Acceptance:
- Exporting a page produces a self-contained HTML file that renders identically to the canvas when opened in a browser.
- All feature tests pass.
- Failure-mode tests pass.
- A new developer can clone, follow the README, and reach a working generation in under 15 minutes.

---

## 22. Progress Tracking Protocol (`progress.md`)

### 22.1 Purpose
`progress.md` is the mutable session log. It answers, at any moment: "What is done? What is in progress? What is next? Where are we blocked?"

### 22.2 Required Sections (Strict Order)
```
# Progress Log

## Current Milestone
M{n} - {name}

## Status
{idle | in_progress | blocked | done}

## Completed Tasks
- [YYYY-MM-DD] M{n}.{short slug}: {one-line description}
- ...

## In Progress
- {task description}
- Started: YYYY-MM-DD
- Last activity: YYYY-MM-DD
- Files touched: {comma-separated list}
- Current state: {brief; what works, what's pending, what's broken}

## Blocked
- {description of blocker, or "None"}
- {what is needed to unblock}

## Decisions Made This Session
- {decision}: {rationale}
- ...

## Spec Change Proposals
- {if a plan.md change is needed, describe here and stop work. Otherwise: "None."}

## Files Created Or Modified This Session
- {path}: {created|modified}: {one-line summary}

## Next Up (Top 3)
1. {task}
2. {task}
3. {task}

## Notes
- {free-form observations}
```

### 22.3 Update Cadence
- At session start: read the file, then update "In Progress" with the date you're starting and a fresh "Status".
- After every meaningful unit of work (one feature, one bug fix, one schema added): append to "Completed Tasks" with today's date.
- At session end: update "Next Up", clear "In Progress" if the task finished, list every file you touched.
- When you make a decision that future-you needs to know: log it under "Decisions Made This Session".
- Never delete entries from "Completed Tasks". Append-only.

### 22.4 Handoff Rule
If you stop work mid-task, "In Progress" must contain enough detail that another agent can resume without asking. The test: can a new agent read `progress.md`, open the files listed under "Files touched", and continue without re-deriving context?

### 22.5 When `plan.md` Conflicts With Reality
If implementation reveals a `plan.md` decision is wrong:
1. STOP implementation.
2. Add an entry under "Spec Change Proposals" in `progress.md` with: the conflicting decision, why it's wrong, the proposed change, and the impact on other sections.
3. Wait for human confirmation before editing `plan.md` or proceeding.

---

## 23. Conventions

### 23.1 Naming
- DB columns: `snake_case`.
- PHP classes: `StudlyCase`.
- Livewire events: `kebab-case` (e.g. `node-selected`).
- Broadcast event names: `StudlyCase` (Laravel convention).
- JSON fields: `snake_case`.
- Tailwind class tokens in `TailwindClassMap`: `kebab-case`.

### 23.2 Time
- All timestamps stored UTC.
- All timestamps in JSON serialized as ISO 8601 with `Z` suffix.

### 23.3 Versioning
- Document `schema_version` starts at `1`. Any breaking change to schema increments and requires a migration path documented in `plan.md`.

### 23.4 Commits
- Commit messages: `[M{n}] {short imperative summary}` (e.g. `[M3] Add card node renderer`).
- One logical change per commit.

### 23.5 Tests
- Feature tests for user-visible flows.
- Unit tests for `Schema/`, `Rendering/`, `Llm/` services.
- A test fixture folder at `tests/fixtures/documents/` holds canonical valid + invalid `Document` JSONs.

### 23.6 LLM Prompt Files
- Live in `resources/prompts/{stage}.system.md`.
- Are read at runtime (not cached at build), so editing a prompt does not require a code change.
- Must include the schema as embedded JSON Schema inside the prompt for the LLM's reference.

### 23.7 Text Encoding
- `plan.md` and `progress.md` are ASCII only. No em-dashes, en-dashes, curly quotes, arrows, section signs, or other non-ASCII characters. Use `->` instead of an arrow, `Sec.` instead of a section sign, `--` or `:` instead of an em-dash, and straight quotes only.

---

## 24. Glossary

| Term | Meaning |
|------|---------|
| Document | The full JSON describing a page. The source of truth. |
| Section | A top-level semantic node in the document tree. |
| Node | Any element within a section (heading, text, container, etc). |
| Element (reusable) | A saved component definition reusable across pages within a project. Stored canonically in `reusable_elements`. |
| Element instance | A reference to a reusable element placed in a section's children. Carries optional overrides. |
| Project library | The set of `ElementDefinition` rows for one project, loaded into pipeline and renderer at runtime. |
| Render | The deterministic process of turning JSON + library into HTML. |
| Pipeline | The sequence of stages from prompt to validated document. |
| Stream panel | The UI panel showing live generation events. |
| Bridge | The small JS file injected into the preview iframe to handle clicks and updates. |
| Safelist | The closed set of Tailwind classes the renderer is allowed to emit. |
| Content Principle | Rule that all visible text must be a child node (Sec. 5.1.1). |

---

## 25. End

This document is complete and locked. The first task is M1. Update `progress.md` before doing anything else.
