# EBQ Architecture Docs

Architectural documentation for the EBQ SEO platform, focused on the **crawl
subsystem** and the **shared single-crawl store** (one crawl per domain, shared
across every user who adds that domain).

## Index

| Doc | What it covers |
|-----|----------------|
| [shared-crawl.md](./shared-crawl.md) | The shared crawl_site store: data model, shared-vs-per-user split, cap windowing, write path, read path, lifecycle (subscribe / charge / GC). **Start here.** |
| [crawl-pipeline.md](./crawl-pipeline.md) | The crawl execution pipeline: frontier → multi-pass loop → page batches → analysis. Jobs, services, value ordering, incremental crawl. |
| [deployment-and-queues.md](./deployment-and-queues.md) | The 2-box topology (web box + worker box), the Redis queues, opcache/FPM, and the deploy gotchas (incl. the one that bit the shared-crawl rollout). |

## The one-paragraph version

A domain used to be crawled **once per website** — if 10 users added
`basepaws.com`, the site was fetched 10 times and re-fetched on 10 schedules. The
shared-crawl rework introduces a **`crawl_sites`** entity (one row per normalized
domain). The four crawl tables (`website_pages`, `website_internal_links`,
`crawl_runs`, `crawl_findings`) are keyed by **`crawl_site_id`**, so the domain is
crawled **once** — at the **max page cap** among its subscribers — and every user
reads that single crawl through a **cap-limited, per-user view**.

## Key invariants (do not break these)

1. **Crawl data is keyed by `crawl_site_id`, never `website_id`.** The crawl
   tables still have a (now-nullable, FK-less) `website_id` column for transitional
   reasons, but nothing reads/writes it. A crawl row belongs to a *domain*, not a
   *website*.
2. **A domain is fetched once.** `CrawlWebsitePagesJob` is `ShouldBeUnique` per
   crawl_site (`uniqueId = "crawl-site-{id}"`); a single in-flight run serves all
   subscribers and extends to a higher cap mid-flight (the pass loop re-reads
   `effective_cap` each pass).
3. **Per-user data never leaks.** Traffic impact and click-based severity are
   computed **read-time** from each website's own Search Console; the shared
   findings store `impact = 0`. Ignore/resolve state lives in the per-website
   `website_finding_states` overlay.
4. **Each user sees only their cap window.** Pages carry a `value_rank` (rank in
   the shared value ordering); reads filter `value_rank <= the owner's plan cap`.
5. **Deleting a website never deletes a shared crawl other users still use.** The
   crawl tables' `website_id` cascade FK was dropped on purpose; GC of a crawl_site
   happens only when its last subscriber leaves.

## Status / known gap at time of writing

The application code (web box) is migrated and verified end-to-end (a synchronous
crawl of basepaws.com produces shared data that two users both read). **The worker
box must run the same code** — until it does, queued crawls are processed by old
code and produce `website_id`-keyed rows the new schema ignores. See
[deployment-and-queues.md](./deployment-and-queues.md#two-box-deploy-gotcha).
