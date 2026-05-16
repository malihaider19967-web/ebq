# 24 — AI Related Posts block

## What the feature does

Server-side rendered `ebq/related-posts` block that calls the backend
related-posts endpoint (cosine-ranked vs the source post's embedding)
and falls back to local same-category posts when the backend is
unreachable.

## Files

- WP block: [`ebq-seo-wp/includes/class-ebq-related-posts-block.php`](../../ebq-seo-wp/includes/class-ebq-related-posts-block.php)
- Backend: [`app/Http/Controllers/Api/V1/RelatedPostsController.php`](../../app/Http/Controllers/Api/V1/RelatedPostsController.php)
- Plan flag: `plan_features.internal_links`

## Pre-conditions

- Plan has `internal_links` on (Pro+).

## Scenarios

### 1. Block insertion

In a post, add the "EBQ → Related posts" block. Set count=5.

✅ Save + view post: an `<aside class="ebq-related-posts">` lists 5
linked items.

### 2. Local fallback

Disconnect the EBQ workspace token. Re-render the post.

✅ Block still emits 5 items (drawn from same-category recents). No
PHP warnings in the error log.

### 3. Cards layout

Switch the block layout attribute to `cards`:

✅ Output uses `<div class="ebq-related-posts__cards">` with anchor +
excerpt cards.

### 4. Plan gate

Switch plan to Free:

✅ Block registration skipped — saved blocks render their fallback
HTML (just the wrapping `<!-- wp:ebq/related-posts /-->` comment that
isn't transformed).
