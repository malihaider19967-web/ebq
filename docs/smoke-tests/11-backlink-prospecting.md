# 11 — Backlink prospecting (persisted)

**MOAT lever:** network effect + AI. The competitor backlink corpus is
populated by every other EBQ user's audits — a first-day Pro user can
already prospect against any audited competitor. AI outreach drafts are
the Pro-tier upsell.

## Surface

| Layer | Path |
|---|---|
| Service | `app/Services/BacklinkProspectingService.php` |
| Controller | `PluginHqController::backlinkProspects()` / `backlinkOutreachDraft()` / `outreachProspectsList()` / `outreachProspectsUpdate()` |
| Routes | `POST /api/v1/hq/backlink-prospects` · `POST /backlink-prospects/draft` · `GET /outreach-prospects` · `POST /outreach-prospects/{id}` |
| Storage | `outreach_prospects` table (`App\Models\OutreachProspect`) |
| WP proxy | `class-ebq-rest-proxy.php::hq_backlink_prospects*` + `hq_outreach_prospects_*` |
| HQ tab | `ebq-seo-wp/src/hq/tabs/ProspectsTab.jsx` |

## Pre-conditions

- `outreach_prospects` table migrated.
- Some `competitor_backlinks` rows already populated (these come from
  other users' page audits via `CompetitorBacklinkService`). For an
  isolated dev environment, manually run a page audit on a known
  competitor URL first to seed the table.

## Scenario 1 — Empty initial state

Fresh website with no saved prospects:

```bash
curl -s "https://ebq.io/api/v1/hq/outreach-prospects?status=new" \
  -H "Authorization: Bearer <TOKEN>" | jq
```

**Pass when:** `prospects: []`, `counts: { new: 0, drafted: 0, ... }` for
all 7 status values.

## Scenario 2 — Find prospects (seeds the table)

```bash
curl -s -X POST "https://ebq.io/api/v1/hq/backlink-prospects" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"competitors":["competitor-with-known-backlinks.com"]}' | jq '.summary'
```

**Pass when** rows appear in `outreach_prospects`:

```sql
SELECT id, referring_domain, status, domain_authority, linked_to_competitors
FROM outreach_prospects WHERE website_id = <WEBSITE_ID> ORDER BY domain_authority DESC LIMIT 10;
```

All new rows have `status='new'` and `first_seen_at = last_seen_at`.

## Scenario 3 — Re-run enriches, doesn't replace

Run a SECOND search with a DIFFERENT competitor that shares some
referring domains:

```bash
curl -s -X POST "https://ebq.io/api/v1/hq/backlink-prospects" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"competitors":["another-competitor.com"]}'
```

**Pass when:**
- Existing rows that match: `linked_to_competitors` array now contains
  BOTH competitor domains; `last_seen_at` bumped.
- Status NOT reset (a row already in `contacted` stays `contacted`).
- Genuinely new domains added with `status='new'`.

## Scenario 4 — Status update

```bash
curl -s -X POST "https://ebq.io/api/v1/hq/outreach-prospects/<ID>" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"status":"contacted","notes":"Sent outreach 2026-04-28 via Twitter DM"}' | jq
```

**Pass when:**
- `outreach_prospects.<ID>.status = 'contacted'`
- `outreach_prospects.<ID>.contacted_at` auto-set to NOW (first time
  crossing into a "we reached out" state)
- `notes` populated

## Scenario 5 — AI outreach draft (Pro tier)

```bash
curl -s -X POST "https://ebq.io/api/v1/hq/backlink-prospects/draft" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "prospect": { "domain":"example-blog.com", "linked_to":["competitor.com"] },
    "our_page_url":"https://your-site.com/best-protein-powder",
    "our_page_title":"Best Plant Protein Powders 2026",
    "our_page_summary":"Independent review of 12 plant protein powders, with lab-tested protein content per scoop."
  }' | jq
```

**Pass when** `ok: true` with non-empty `subject` (≤140 chars) + `body`
(≤1800 chars). Status auto-flips `new → drafted` on the persisted row;
`latest_draft` populated.

## Scenario 6 — Free tier rejected on draft

Set tier=free, repeat Scenario 5. **Pass when** HTTP 402 with
`error: "tier_required"`.

## Scenario 7 — HQ tab workflow

Open EBQ HQ → Prospects.

**Pass when:**
1. Tab opens to saved working list (filtered by `new` by default).
2. Status pills with counts at the top.
3. "+ Find more prospects" collapsible at the top — adding domains
   enriches the list, doesn't replace.
4. Per-row status dropdown — changing it persists immediately.
5. Click "Open" / "Draft" → expandable row shows the AI draft (if any)
   + notes editor.
6. "Copy email" button copies subject+body to clipboard.

## Acceptance summary

| Check | Pass condition |
|---|---|
| Persistent across sessions | Re-opening tab shows same prospects |
| Re-run merges, doesn't replace | `linked_to_competitors` accumulates |
| Status workflow | All 7 statuses reachable, `contacted_at` auto-stamped |
| Draft persists on the row | `latest_draft` JSON survives reload |
| Pro gate works | 402 on free tier for draft endpoint |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| Tab opens to empty list even after running search | `outreach_prospects` migration not run | `php artisan migrate` |
| Re-run wipes existing prospects | `upsertProspects()` not called or query bug | Check `BacklinkProspectingService::upsertProspects()` exists in deployed code |
| `prospects: []` despite searching | No `competitor_backlinks` rows for given competitors | Run a page audit on a competitor URL first to seed |
| Draft endpoint returns parse-failed | Mistral output malformed | `tail laravel.log \| grep "BacklinkProspectingService"` |
