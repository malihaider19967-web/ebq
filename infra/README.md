# EBQ infrastructure & architecture docs

Engineering documentation for the EBQ SEO platform — how it's built and how it runs.

## Contents

| Area | Doc |
|---|---|
| **Crawler subsystem** (the big one) | [crawler/](./crawler/README.md) — shared single-crawl store: architecture, data model, pipeline, read path, findings/scoring, adjacent systems, operations, known issues. |
| **Deployment & queues** | [deployment-and-queues.md](./deployment-and-queues.md) — the 2-box topology (web + worker), Redis queues, opcache/FPM rules, the deploy sequence, and the shared-crawl rollout postmortem. |

## The platform in one paragraph

EBQ crawls a client's website, pulls their Google Search Console + Analytics data, and turns
both into SEO findings, growth reports, rank tracking, and a WordPress-plugin API. The
heaviest subsystem is the **crawler**, which was re-architected into a **shared single-crawl
store**: each domain is crawled once and shared across every user who added it, read through a
per-user cap-limited, privacy-scoped view. Start at [crawler/](./crawler/README.md).

## Topology at a glance

```
 WEB BOX 10.0.0.2                         WORKER BOX 10.0.0.3 (Docker, php8.3)
  • PHP-FPM (web app, ~20 workers)         • 5× queue:work --queue=crawl
  • supervisor: interactive ×2, default,   • 1× queue:work --queue=sync
    schedule:work                          • bind-mounts /var/www/ebq, rsync deploy
        └──────── shared MySQL `ebq` + shared Redis ────────┘
```

See [deployment-and-queues.md](./deployment-and-queues.md) for the details and the gotchas.

> Safety: this runs on a **production server with no DB backups** (binary logging off — data
> loss is permanent). Never run destructive DB commands without per-command confirmation; tests
> must run on sqlite `:memory:`. See `CLAUDE.md` at the repo root.
