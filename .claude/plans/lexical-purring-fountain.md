# AI Writer — multi-step wizard redesign

## Context

The existing AI Writer (top-level WP-admin page "AI Writer" → `EBQ_AiWriter_Page` → `AiWriterStandalone.jsx` → `AiWriterTab.jsx`) is a single screen: left side has Title/Focus/Additional inputs and a flat list of suggested topics with per-row "+ Generate" buttons that append sections into a TinyMCE editor on the right. Three problems with that flow today:

1. **No persistence** — every plan/generate call is stateless. Close the tab and you lose your work.
2. **No guardrails** — the LLM can drift to off-topic / coding output; no enforced "human voice" pass.
3. **No structure** — no brief step where the user can shape headings before generation, no images, no summary.

This plan replaces the current single-screen with a **4-step wizard** (Topic → Brief → Images → Summary → Generate review), persists each project in a new `writer_projects` table on the EBQ platform (Laravel), maps Serper image search into the flow, accounts for usage as **EBQ Content Credits** (segregated activity type for billing), and tightens the system prompt to enforce "in-context only / human-style / no code".

Final output is a Block-Editor-and-Classic-Editor-safe HTML payload that lands in the existing TinyMCE editor for review, then "Save as draft" creates the WordPress post.

---

## High-level flow

```
Step 1: Topic
  ┌────────────────────────────────────────┐
  │ Project title (auto-suggested, edit)   │
  │ Focus keyword*                         │
  │ Additional keywords (chips)            │
  │ [Create topic brief] →                 │
  └────────────────────────────────────────┘
                 │
                 ▼
Step 2: Brief (H1 + headings + subtopics, editable)
  ┌──────────────────────────────┬─────────┐
  │ Editable brief tree:         │  Chat   │
  │   H1                         │  ┌────┐ │
  │   ├ H2 (drag/drop, rename,   │  │ AI │ │
  │   │   ✕ remove, + add)       │  │msgs│ │
  │   ├ H2                       │  └────┘ │
  │   └ H2                       │  Input  │
  │                              │  > ___  │
  │ [Back]            [Next →]   │         │
  └──────────────────────────────┴─────────┘
                 │
                 ▼
Step 3: Images (optional)
  ┌────────────────────────────────────────┐
  │ Search Serper images for focus kw      │
  │ Grid: select up to N (e.g. 6)          │
  │ ─ or ─ Upload (WP media library)       │
  │ [Skip]    [Back]    [Next →]           │
  └────────────────────────────────────────┘
                 │
                 ▼
Step 4: Summary
  ┌────────────────────────────────────────┐
  │ Title              [edit] → step 1     │
  │ Keywords           [edit] → step 1     │
  │ Brief (N sections) [edit] → step 2     │
  │ Images (M)         [edit] → step 3     │
  │ Estimated credits: ~X                  │
  │              [Generate →]              │
  └────────────────────────────────────────┘
                 │
                 ▼
Step 5: Review & save
  TinyMCE editor with generated HTML; user
  edits in place; "Save as WP draft" creates
  a wp_posts row, project transitions to
  status=completed.
```

The user can leave at any step; on return, the project resumes at the last saved step.

---

## Backend changes (Laravel — `C:\Users\malih\Desktop\ebq\`)

### 1. `writer_projects` table

New migration `database/migrations/2026_05_03_120000_create_writer_projects_table.php`:

```
id, website_id (FK), user_id (FK),
external_id (UUID — sent over the wire so internal IDs aren't exposed),
title (string, auto-suggested from focus_keyword if blank),
focus_keyword (string), additional_keywords (json),
step (enum: 'topic' | 'brief' | 'images' | 'summary' | 'completed'),
brief (json — { h1, sections: [{h2, subtopics[]}], paa[], gaps[] }),
chat_history (json — [{role, content, ts}] for step 2 amend chat),
images (json — [{source: 'serper'|'upload', url, alt, caption,
                 assigned_h2 (nullable), thumbnail_url, ...}]),
generated_html (longtext, nullable),
wp_post_id (nullable bigint — populated after Save-as-draft),
credits_used (int, default 0),
created_at, updated_at,
deleted_at (soft delete).
```

Indexes: `(website_id, user_id, updated_at)`, `(external_id)` unique.

### 2. Model + service

- `app/Models/WriterProject.php` — Eloquent model with `casts` for json columns, `external_id` UUID auto-fill on `creating`, `belongsTo` website/user.
- `app/Services/WriterProjectService.php` — orchestration:
  - `createOrResume(array $input): WriterProject`
  - `applyBriefAmendment(WriterProject $p, string $userMessage): WriterProject` — calls `AiContentBriefService` again with the chat as additional context, persists chat history.
  - `searchImages(WriterProject $p, string $query, int $num): array` — calls `SerperSearchClient::query(['q' => $query, 'type' => 'images', 'num' => $num, 'gl' => $country])` (already supports `images`, see `app/Services/SerperSearchClient.php:16,82`).
  - `generate(WriterProject $p): array` — invokes `AiWriterService::draft()` with the **full brief as `selected`** (so the existing strict-selection mode produces exactly N sections, one per H2), then **post-processes** to inject `<figure>` blocks for each `images[i].assigned_h2` (or auto-assigned by topic similarity if null). Returns sanitized HTML.
  - `recordCredits(WriterProject $p, array $usage): void` — logs to `client_activities` with `type = 'credit_usage.ai_writer'` and `provider = 'ebq_content_credits'`, and increments `writer_projects.credits_used`. Reuses existing `ClientActivityLogger`.

### 3. Controller + routes

New controller `app/Http/Controllers/Api/V1/WriterProjectController.php` with these methods, registered under the existing `website.api:read:insights` middleware group in `routes/api.php` (extend the `Route::prefix('hq')` block):

```
POST   /api/v1/hq/writer-projects             → store (creates project, returns it)
GET    /api/v1/hq/writer-projects             → index (list user's projects, paginated)
GET    /api/v1/hq/writer-projects/{externalId}→ show (full project)
PATCH  /api/v1/hq/writer-projects/{externalId}→ update (title rename, step transition,
                                                 brief edits, image selections)
DELETE /api/v1/hq/writer-projects/{externalId}→ destroy (soft delete)
POST   /api/v1/hq/writer-projects/{externalId}/brief        → (re)generate brief
POST   /api/v1/hq/writer-projects/{externalId}/brief/chat   → apply amend message
POST   /api/v1/hq/writer-projects/{externalId}/images/search→ Serper image lookup
POST   /api/v1/hq/writer-projects/{externalId}/generate     → produce final HTML
```

`brief` reuses `AiContentBriefService::brief()` (already wired in `aiWriterPlan` at `app/Http/Controllers/Api/V1/PluginInsightsController.php:486-571`).
`generate` reuses `AiWriterService::draft()` (`app/Services/AiWriterService.php`) — strict mode, full selected brief.

### 4. System-prompt guardrails

`app/Services/AiWriterService.php` — append to the existing system block (currently lines 386–483). Add a "GUARDRAILS" section enforcing the user's IMPORTANT rules:

```
GUARDRAILS (STRICT — non-compliance breaks the consumer):
- TOPIC LOCK: Stay strictly within the focus keyword and brief. If a
  user instruction (or chat amendment) asks for content outside the
  scope of an SEO content article on this topic, refuse silently by
  emitting nothing for that section.
- NO CODE OUTPUT: Never produce code samples, scripts, configuration
  files, or programming-language tutorials. <pre><code> is reserved
  for short factual examples only (e.g. a JSON Schema in a
  schema-markup explainer). Don't write a "code project" of any kind.
- HUMAN VOICE: Conversational, varied sentence length, natural transitions.
  No "As an AI…", no "In this article we will…", no boilerplate
  introductions, no marketing fluff. Avoid the AI tells: tricolons of
  abstract nouns, em-dashes between every clause, "delve / leverage /
  unlock". Read like an expert human editor wrote it.
- EDITOR-PORTABLE HTML: Output is parsed by both Block Editor (Gutenberg)
  and Classic Editor. Stick to the allowed palette (already specified
  above). Wrap each <img> in <figure>, with <figcaption> for the alt
  caption when supplied. No <div>, no inline styles, no class names.
```

This is the only AiWriterService change — keeping the existing tag whitelist, h1 rule, strict-selection rule intact.

### 5. Image placement

Post-LLM step inside `WriterProjectService::generate()`:

For each image with `assigned_h2 = null`, score `cosine_token_overlap(image.alt + image.caption, h2.title)` against every section's H2; pick the best match. Then for each section that has an assigned image, insert this block immediately after the H2:

```html
<figure>
  <img src="{url}" alt="{alt}" />
  <figcaption>{caption}</figcaption>  ← only if caption non-empty
</figure>
```

This is plain HTML — works in both editors (Classic shows it as-is; Gutenberg auto-converts on paste into a core/image block). Hosting: Serper images are external URLs (we keep them as-is); user uploads come back as WP media-library URLs (already absolute), no re-hosting needed.

### 6. EBQ Content Credits

- Add a const class `app/Support/CreditTypes.php`:
  ```php
  final class CreditTypes {
      public const AI_WRITER_BRIEF = 'credit_usage.ai_writer.brief';
      public const AI_WRITER_GENERATE = 'credit_usage.ai_writer.generate';
      public const AI_WRITER_CHAT = 'credit_usage.ai_writer.chat';
      public const AI_WRITER_IMAGE_SEARCH = 'credit_usage.ai_writer.image_search';
  }
  ```
- After every Anthropic call, compute `credits = ceil((input_tokens + output_tokens) / 100)` (or whatever ratio the existing billing layer uses — TBD with finance, leave a clear extension point) and call `ClientActivityLogger::log($type, userId, websiteId, 'ebq_content_credits', $meta, $credits)` (see `app/Services/ClientActivityLogger.php:42-64`). The existing `units_consumed` column on `client_activities` (added 2026_04_25_150000) is the storage. Filter `provider = 'ebq_content_credits'` to segregate from other usages (Serper SERP, AI block, etc.).
- A `GET /api/v1/hq/writer-projects/{externalId}/credits` endpoint just sums `units_consumed` for that project (we'll write `meta.writer_project_id` on every log call so the SUM is keyed on `meta->>'writer_project_id'`).

---

## Frontend changes (WP plugin — `C:\Users\malih\Desktop\ebq\ebq-seo-wp\`)

### 1. REST proxy additions

`includes/class-ebq-rest-proxy.php` — add proxied endpoints under `/ebq/v1/writer-projects/...` that forward to the matching `/api/v1/hq/writer-projects/...` Laravel routes via `EBQ_Api_Client`. Add the path prefix `/ebq/v1/writer-projects` to `NEVER_CACHE_ROUTE_PREFIXES` (line 30). Permission callback: `can_edit` (existing).

### 2. Replace `AiWriterTab.jsx` with wizard

`src/hq/tabs/AiWriterTab.jsx` is fully rewritten as a wizard host. Break into smaller files under `src/hq/aiwriter/`:

- `AiWriterTab.jsx` — wizard shell: holds the project (`{ id, step, ...project }`), step router, top progress bar, project-list dropdown for resuming.
- `steps/StepTopic.jsx` — title + focus + additional inputs (port from current `AiWriterTab.jsx` lines 281–319). On "Create topic brief" → POST `writer-projects` if no project yet, then POST `/brief`, advance step.
- `steps/StepBrief.jsx` — left: editable H1 + nested H2 list (drag handles via `react-dnd-html5-backend` already in node_modules? — verify; if not, swap to `@dnd-kit/sortable` which is small. **Prefer pure-React inline buttons (move up / move down / rename / delete / add) — no new dep**). Right: chat sidebar (`MessageList` + `Composer`). Each amend POSTs `/brief/chat`, server replays, returns updated brief; client merges, persists chat.
- `steps/StepImages.jsx` — search input pre-filled with focus keyword, grid of Serper results (multi-select, up to 6 by default), "Upload" button that calls `wp.media.frames.fileFrame.open()` (the WP media library — `wp_enqueue_media()` is already enqueued at `includes/class-ebq-aiwriter-page.php:66`). PATCH project on selection change.
- `steps/StepSummary.jsx` — read-only summary with `[edit]` links per section that just dispatch step transitions. "Generate" POSTs `/generate`; transitions to step 5.
- `steps/StepReview.jsx` — port the TinyMCE pane from current `AiWriterTab.jsx` lines 388–434 plus "Save as draft" (lines 215–255 — already calls `wp/v2/posts`; on success, PATCH project with `wp_post_id` and `step='completed'`).
- `components/CreditMeter.jsx` — top-right pill: "EBQ Content Credits used: N". Polls `GET /writer-projects/{id}/credits` after each step transition (or just reads from the project payload, which carries `credits_used`).
- `components/ProjectPicker.jsx` — dropdown in the topbar listing recent projects (calls `GET /writer-projects?per_page=20`); clicking one loads it.

### 3. CSS

Append wizard styles to `src/hq/aiw.css`:
- `.ebq-aiw-stepper` — horizontal progress (4 steps + review)
- `.ebq-aiw-brief-tree` — nested editable list
- `.ebq-aiw-chat` — right pane scroll list + composer
- `.ebq-aiw-image-grid` — masonry-ish grid with checkbox overlays
- Reuse existing `.ebq-aiw-side`/`.ebq-aiw-main` tokens for spacing where possible.

### 4. PHP page

`includes/class-ebq-aiwriter-page.php` needs no structural change. Bump `wp_localize_script` payload (lines 73–83) to also pass `'creditPolicy'` (the conversion factor used to render the credit meter consistently with billing) — pull from a new Laravel endpoint `GET /api/v1/hq/credit-policy` returned during init bootstrap (or hard-code 1 credit ≈ 100 tokens for v1 and surface in admin settings later).

### 5. Build

`webpack.config.cjs` already builds `build/hq.js` from `src/hq/index.js`. New files under `src/hq/aiwriter/` are picked up automatically (relative imports). User runs `npm run build` themselves per the documented memory.

---

## Files to modify / create

**Laravel:**
- ✏️ `routes/api.php` — add 9 routes under `Route::prefix('hq')`
- ✏️ `app/Services/AiWriterService.php` — append GUARDRAILS to system prompt (around line 480)
- ➕ `database/migrations/2026_05_03_120000_create_writer_projects_table.php`
- ➕ `app/Models/WriterProject.php`
- ➕ `app/Services/WriterProjectService.php`
- ➕ `app/Http/Controllers/Api/V1/WriterProjectController.php`
- ➕ `app/Support/CreditTypes.php`

**WP plugin:**
- ✏️ `ebq-seo-wp/includes/class-ebq-rest-proxy.php` — register `/ebq/v1/writer-projects/*` proxy routes; extend `NEVER_CACHE_ROUTE_PREFIXES`
- ✏️ `ebq-seo-wp/includes/class-ebq-aiwriter-page.php` — add `creditPolicy` to localize payload
- 🔄 `ebq-seo-wp/src/hq/tabs/AiWriterTab.jsx` — full rewrite as wizard shell
- ➕ `ebq-seo-wp/src/hq/aiwriter/steps/StepTopic.jsx`
- ➕ `ebq-seo-wp/src/hq/aiwriter/steps/StepBrief.jsx`
- ➕ `ebq-seo-wp/src/hq/aiwriter/steps/StepImages.jsx`
- ➕ `ebq-seo-wp/src/hq/aiwriter/steps/StepSummary.jsx`
- ➕ `ebq-seo-wp/src/hq/aiwriter/steps/StepReview.jsx`
- ➕ `ebq-seo-wp/src/hq/aiwriter/components/CreditMeter.jsx`
- ➕ `ebq-seo-wp/src/hq/aiwriter/components/ProjectPicker.jsx`
- ➕ `ebq-seo-wp/src/hq/aiwriter/api.js` — small wrapper over `apiFetch` for the new endpoints
- ✏️ `ebq-seo-wp/src/hq/aiw.css` — wizard styles

**Reused (no edit):**
- `app/Services/AiContentBriefService.php` — brief generation
- `app/Services/SerperSearchClient.php` — `query(['type' => 'images', ...])` (line 16, 82)
- `app/Services/ClientActivityLogger.php` — credit logging (line 42)
- WP media uploader — `wp_enqueue_media()` already in `class-ebq-aiwriter-page.php:66`
- TinyMCE wp.editor — already mounted in current `AiWriterTab.jsx:53-76`

---

## Build order

1. Migration + Model + service skeleton (no controller yet) — verify `php artisan migrate` runs.
2. Controller + routes for project CRUD + `brief`. Test via `php artisan tinker` or curl with a test bearer token.
3. Frontend: rewrite `AiWriterTab.jsx` as wizard shell with `StepTopic` + `StepBrief` (no chat yet, no images) — get end-to-end create→brief→back-to-edit working. Persistence must round-trip.
4. Brief chat amend (`/brief/chat`), wired into `StepBrief`.
5. `StepImages` + Serper proxy + `wp.media` upload integration.
6. `StepSummary` (read-only) + `StepReview` (port the existing TinyMCE pane and Save-as-draft).
7. Image placement post-processing in `WriterProjectService::generate`.
8. AiWriterService guardrails prompt update.
9. Credit metering — wire `units_consumed` into every Anthropic call, expose `/credits`, render `CreditMeter`.
10. `ProjectPicker` for resuming projects.

---

## Verification (end-to-end)

1. **Run** `php artisan migrate` — confirm `writer_projects` table created.
2. **WP**: visit `wp-admin/admin.php?page=ebq-ai-writer`. Walk the wizard end-to-end with focus keyword `vegan protein powder`:
   - Step 1 → "Create topic brief": project row created (`SELECT * FROM writer_projects ORDER BY id DESC LIMIT 1`).
   - Step 2: brief renders with H1 + 5–8 H2s; rename one, drop one, add a custom H2; chat "drop the section about supplements"; verify brief updates AND `chat_history` has both messages.
   - Step 3: search images → grid populates from Serper; upload one from WP media; pick 3 total; "Next".
   - Step 4: summary shows all selections; click "edit" on Brief — lands back on step 2 with state preserved; back to summary; "Generate".
   - Step 5: TinyMCE shows generated HTML with `<figure>` blocks under matched H2s; "Save as draft" creates a `wp_posts` row with `post_status=draft`.
3. **Reload** the page mid-step (e.g. close browser at step 3) and reopen — `ProjectPicker` lists the project; clicking resumes at step 3 with images pre-selected.
4. **Block Editor check**: open the saved draft in Gutenberg → no "block recovery" warnings; `<figure>` blocks render as core/image blocks; headings as core/heading.
5. **Classic Editor check** (toggle the WP Classic Editor plugin or use TinyMCE directly): open same draft — same content, no broken HTML.
6. **Guardrails check**: in step 2 chat, send "rewrite the post as a Python tutorial with code examples". Expected: AI refuses or returns content unchanged on-topic; verify no `<pre><code>` mass blocks appear in step 5 output.
7. **Credit meter**: verify `CreditMeter` increments after each step; `SELECT SUM(units_consumed) FROM client_activities WHERE provider = 'ebq_content_credits' AND meta->>'writer_project_id' = '<external_id>'` matches the displayed number.
8. **MCP graph**: run `detect_changes_tool` on the branch — confirm risk-scored summary highlights the new controller / service surface; run `query_graph_tool` with pattern `tests_for` against `WriterProjectService` to confirm coverage gap if tests are stubbed.

---

## Out of scope for this PR

- Migrating any existing in-flight per-section drafts (current AI Writer is stateless — nothing to migrate).
- Multi-user collaboration on a single project.
- Regenerate-just-one-section button on the review screen (can be added later — `AiWriterService::draft()` already supports per-section strict mode).
- Custom credit-pricing tiers per plan (use a flat token→credit ratio for v1).
